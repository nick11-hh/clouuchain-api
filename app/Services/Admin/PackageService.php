<?php

namespace App\Services\Admin;

use App\Jobs\FulfillmentOrderJob;
use App\Jobs\FulfillmentOrderJobV2;
use App\Jobs\LogisticsPlaceJobV2;
use App\Lib\Code;
use App\Models\Order;
use App\Models\Order as OrderModel;
use App\Models\OrderItemMapping;
use App\Models\OrderItemStock;
use App\Models\OrderLineItem;
use App\Models\OutboundOrder;
use App\Models\Package;
use App\Models\PackageAddress;
use App\Models\PackageItem;
use App\Models\ShopOrderLogs;
use App\Models\Stock;
use App\Models\StockLockLog;
use App\Models\AdminOperationLog;
use App\Models\ThirdPartyWarehouseConfig;
use App\Services\Base\OrderBaseService;
use App\Services\Base\PackageBaseService;
use App\Services\Base\StockService;
use App\Services\Base\SystemConfigService as SystemConfigBaseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use PayPal\Api\Address;
use App\Exceptions\AccidentException;

class PackageService extends BaseService
{
    public $filterRules = [
        'status'             => ['=', 'status'],
        'logistics_status'   => ['=', 'logistics_status'],
        'stock_status'       => ['=', 'stock_status'],
        'sku_status'         => ['=', 'sku_status'],
        'package_sn'         => ['=', 'package_sn'],
        'created_at'         => ['between', ['begin_date', 'end_date']]
    ];

    private PackageItem $packageItem;

    public function __construct()
    {
        $this->model = new Package();
        $this->packageItem = new PackageItem();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with(['items.lineItem', 'packageAddress', 'orders:id,order_id,name', 'orders.lineItems']);
        $this->queryCondition();
        $this->query->latest();
        return parent::index();
    }

    public function show($id)
    {
        return $this->model::with(['items.lineItem', 'packageAddress'])->findOrFail($id);
    }

    public function count()
    {
        unset($this->filters['status']);
        unset($this->formData['logistics_status']);
        unset($this->formData['stock_status']);
        unset($this->formData['sku_status']);
        unset($this->formData['split_merge_status']);
        $this->setFilter();
        $this->queryCondition();
        return $this->query->selectRaw('count(*) as count, status')->groupBy('status')->get();
    }

    public function queryCondition()
    {
        if (!empty($this->formData['split_merge_status'])) {
            $this->query->where('split_merge_status', $this->formData['split_merge_status']);
        }
        if (!empty($this->formData['order_id'])) {
            $this->query->whereHas('orders', function ($query) {
                $query->where('order_id', $this->formData['order_id']);
            });
        }
    }

    public function subStatusCount($type)
    {
        //删除对应状态查询条件
        unset($this->formData[$type]);
        $this->filters = [];
        $this->setFilterRules();
        $this->setFilter();
        if (!empty($this->formData['status'])) {
            $this->query->where('status', $this->formData['status']);
        }
        switch ($type) {
            case 'sku_status':
            case 'logistics_status':
            case 'stock_status':
                return (clone $this->query)->select("{$type} as status", DB::raw('count(*) as count'))->groupBy($type)->get();
            case 'split_merge_status':
                $statusList = (clone $this->query)->select("{$type} as status", DB::raw('count(*) as count'))->groupBy($type)->get();
                $mergeCount = PackageAddress::query()->whereHas('package', function ($query) {
                    $query->where('split_merge_status', Package::PACKAGE_DEFAULT)
                        ->where('status', Package::STATUS_WAIT_DEAL)->whereIn('logistics_status', [Package::LOGISTICS_WAIT_APPLY, Package::LOGISTICS_APPLY_FAILURE]);
                })->groupBy('unique_key')->selectRaw('unique_key, COUNT(*) as count' )->havingRaw('COUNT(*) > 1')->count();
                $statusList[] = ['status' => 0, 'count' => $mergeCount];
                return $statusList;
            default:
                throw new AccidentException('非法状态', Code::OPERATE_FAIL);
        }
    }

    public function createByOrder($order)
    {
        return DB::transaction(function () use ($order) {
            $params = [
                'order_id'               => $order->id,
                'express_companies_id'   => $order->channel->express_companies_id ?? 0,
                'express_companies_code' => $order->logistics_provider_code ?? '',
                'express_channel_code'   => $order->channel->code ?? '',
            ];
            $packageData = $this->model::init($params);
            $package = $this->model::query()->create($packageData);
            foreach ($order->lineItems as $item) {
                $this->packageItem::query()->create([
                    'package_id' => $package->id,
                    'shop_order_id' => $order->id,
                    'shop_order_item_id' => $item->id,
                    'quantity' => $item->quantity
                ]);
            }
            if (!empty($order->shippingAddress)) {
                $addressData = PackageAddress::init($package->id, $order->shippingAddress->toArray());
                PackageAddress::query()->create($addressData);
            }
            $this->updatePackageSkuStatus($package);
            return $package;
        });
    }

    /**
     * 申请运单
     */
    public function applyLogistics($params)
    {
        $ids = $params['ids'] ?? [];
        if (empty($ids)) throw new AccidentException('请选择需要申请的包裹', Code::OPERATE_FAIL);
        if (ThirdPartyWarehouseConfig::getConfig()) {
            throw new AccidentException('您已启用第三方ERP进行履约，请到对方系统申请运单！', Code::OPERATE_FAIL);
        }
        DB::transaction(function () use ($ids) {
            Package::query()->whereIn('id', $ids)->update([
                'logistics_status' => Package::LOGISTICS_PROGRESSED,
            ]);
            $orderIds = PackageItem::query()->whereIn('package_id', $ids)->get()->pluck('shop_order_id')->toArray();
            // 将订单状态更新为运单号申请中
            Order::query()->whereIn('id', $orderIds)->update([
                'logistics_status' => Order::LOGISTICS_PROGRESSED,
                'fulfillment_platform' => ThirdPartyWarehouseConfig::PLATFORM_YUNLIANTIAO
            ]);

            // 分发申请运单号任务到队列中
            foreach ($ids as $packageId) {
                dispatch(new LogisticsPlaceJobV2($packageId, []))->delay(2);
            }
        });

        return true;
    }

    /**
     * 移动配货中
     */
    public function moveToStock($params)
    {
        $ids = $params['ids'] ?? [];
        if (empty($ids)) throw new AccidentException('请选择需要移入的包裹', Code::OPERATE_FAIL);
        DB::transaction(function () use ($ids) {
            $syncWaybillNumber = SystemConfigBaseService::getConfigValue('sync_waybill_number');
            $packages = Package::query()->whereIn('id', $ids)->get();
            foreach ($packages as $package) {
                if ($package->status != Package::STATUS_WAIT_DEAL && $package->status != Package::STATUS_DISTRIBUTION) {
                    throw new AccidentException('当前包裹状态不允许移入配货中', Code::OPERATE_FAIL);
                }

                if ($package->logistics_status != Package::LOGISTICS_APPLY_SUCCESS) {
                    throw new AccidentException('移入已配货需要先申请运单', Code::OPERATE_FAIL);
                }

                $package->status = Package::STATUS_DISTRIBUTION;
                $package->save();
                $this->createOutboundData($package);
                $package->orders->each(function ($order) {
                    $order->order_status = Order::STATUS_APPLY_NUM;
                    $order->save();
                    (new OrderBaseService($order))->syncPackageStockStatus();
                });
                if($syncWaybillNumber == 2) {
                    (new PackageBaseService($package))->packagePlatformDelivery();
                }
            }
        });

        return true;

    }

    /**
     * 仓库出单单
     * 来源于包裹移动到配货中
     * @param $package
     * @param int $type
     * @return bool
     * @throws Exception
     */
    public function createOutboundData($package, int $type = 1)
    {
        // 订单配货成功的不再配货
        if ($package->stock_status === Order::STOCK_SUCCESS) return false;
        $package->stock_status = Package::STOCK_SUCCESS;

        // 已经存在管理的出库单
        if (!empty($package->outboundOrder)) {
            return $package->save();
        }

        $package->items->each(function ($item) use ($package, $type) {
            $mapping = OrderItemMapping::query()->with('goodsSku')->where('platform_variant_id', $item->lineItem->variant_id)->first();
            if (empty($mapping)) throw new AccidentException('订单未映射本地商品', Code::OPERATE_FAIL);

            //默认使用是“本企业”的库存
            $customerId = 0;
            $order = Order::query()->find($item->shop_order_id);
            //使用客户的库存
            if (!empty($order->use_customer_stock)) $customerId = $order->customer_id;

            $stock = Stock::query()->where('sku_id', $mapping->goods_sku_id)->where('custom_id', $customerId)->first();

            if ((empty($stock) || $stock->quantity < $item->quantity)) {
                $package->stock_status = Package::STOCK_LACK;
                $package->save();
                if ($type == 2) {
                    throw new AccidentException('库存不足', Code::OPERATE_FAIL);
                } else {
                    return false;
                }
            }
            return true;
        });

        // 有货
        if ($package->stock_status != Package::STOCK_LACK) {
            $package->stock_status = Package::STOCK_SUCCESS;
            $package->save();
            $this->createOutboundOrder($package);
        }
        return true;
    }


    /**
     * 包裹创建出库单
     */
    public function createOutboundOrder($package)
    {

        $package->stock_status = Order::STOCK_SUCCESS;
        $package->save();

        // 创建出库单
        $outboundOrderService = new OutboundOrderService();
        $logisticInfo = $package->logisticsApply;
        $address = $package->packageAddress;
        $outboundOrderService->createByPacakge($package, $logisticInfo, $address);
        return true;
    }

    /**
     * 分拆包裹
     */
    public function split($params)
    {
        validator($params, [
            'package_id'                => 'required|int',
            'package_list'              => 'required|array',
            'package_list.*.items'      => 'required|array',
            'package_list.*.items.*.id' => 'required|int',
            'package_list.*.items.*.quantity' => 'required|int|min:1',
        ])->validate();
        if (count($params['package_list']) <= 1) throw new AccidentException('拆包包裹数量必须大于1', Code::OPERATE_FAIL);
        return DB::transaction(function () use ($params) {
            $package = Package::query()->with('orders')->findOrFail($params['package_id']);
            foreach ($params['package_list'] as $value) {

                $originPackage = $package->toArray();
                $originPackage['split_merge_status'] = Package::PACKAGE_SPLIT;
                $packageData = Package::init($originPackage);
                $newPackage = Package::query()->create($packageData);

                foreach ($value['items'] as $item) {
                    $packageItem = PackageItem::query()->findOrFail($item['id']);
                    PackageItem::query()->create([
                        'package_id' => $newPackage->id,
                        'shop_order_id' => $packageItem->shop_order_id,
                        'shop_order_item_id' => $packageItem->shop_order_item_id,
                        'quantity' => $item['quantity']
                    ]);
                }

                $addressData = PackageAddress::init($newPackage->id, $package->packageAddress->toArray());
                PackageAddress::query()->create($addressData);

                $this->updatePackageSkuStatus($newPackage);
            }
            $package->split_merge_status = Package::PACKAGE_SPLIT;
            $package->save();
            $package->items()->delete();
            $package->packageAddress()->delete();
            $package->delete();
            $package->items()->delete();

            foreach ($package->orders as $order) {
                $order->split_merge_status = Package::PACKAGE_SPLIT;
                $order->save();
                ShopOrderLogs::addLog([
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_ORDER_MERGE,
                    'content' => '订单拆包',
                ]);
            }
            return true;
        });
    }

    public function splitList()
    {
        $splitOrder = Order::query()->with('packages.items.lineItem')->where('order_status', Order::STATUS_PENDING)
            ->where('split_merge_status', Package::PACKAGE_SPLIT)->get();
        return $splitOrder;
    }

    /**
     * 还原包裹拆分
     */
    public function splitRollback($params)
    {
        $orderIds = $params['ids'] ?? [];
        $orders = Order::query()->with('packages.items')->whereIn('id', $orderIds)->get();
        return DB::transaction(function () use ($orders) {
            $logData = [];
            foreach ($orders as $order) {
                if ($order->order_status != Order::STATUS_PENDING) {
                    throw new AccidentException('只有已付款状态的订单才能还原拆单', Code::OPERATE_FAIL);
                }

                $order->packages()->delete();
                $this->createByOrder($order);
                $order->split_merge_status = Package::PACKAGE_DEFAULT;
                $order->save();
                ShopOrderLogs::addLog([
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_ORDER_MERGE,
                    'content' => '订单取消拆包',
                ]);
            }

            return true;
        });
    }

    /**
     * 包裹合并
     */
    public function merge($params)
    {
        validator($params, [
            'merge_package_list' => 'required|array',
            'merge_package_list.*.unique_key'        => 'required|string',
            'merge_package_list.*.package_list'      => 'required|array',
            'merge_package_list.*.package_list.*.id' => 'required|int'
        ])->validate();
        return DB::transaction(function () use ($params) {

            $logData = [];
            foreach ($params['merge_package_list'] as $mergePackage) {
                $packageList = Package::query()->whereIn('id', array_column($mergePackage['package_list'], 'id'))->get();
                if (count($packageList) < 2) {
                    throw new AccidentException('合包包裹数量必须大于1', Code::OPERATE_FAIL);
                }
                $originPackage = $packageList[0]->toArray();
                $originPackage['split_merge_status'] = Package::PACKAGE_MERGE;
                $originPackage['order_id'] = 0;
                $packageData = Package::init($originPackage);
                $newPackage = Package::query()->create($packageData);

                $addressData = PackageAddress::init($newPackage->id, $packageList[0]->packageAddress->toArray());
                PackageAddress::query()->create($addressData);

                foreach ($packageList as $package) {
                    foreach ($package->items as $item) {
                        PackageItem::query()->create([
                            'package_id' => $newPackage->id,
                            'shop_order_id' => $item->shop_order_id,
                            'shop_order_item_id' => $item->shop_order_item_id,
                            'quantity' => $item->quantity
                        ]);
                    }
                    $package->save();
                    $package->items()->delete();
                    $package->packageAddress()->delete();
                    $package->delete();
                }

                $orderIds = $newPackage->orders->pluck('order_id')->toArray();
                foreach ($newPackage->orders as $order) {
                    $order->split_merge_status = Package::PACKAGE_MERGE;
                    $order->save();
                    ShopOrderLogs::addLog([
                        'order_id' => $order->id,
                        'operator_type' => ShopOrderLogs::OPERATOR_TYPE_ORDER_MERGE,
                        'content' => '订单合包，合包订单：' . implode(',', $orderIds),
                    ]);

                    $logData[] = [
                        'order_id' => $order->id,
                        'operator_type' => ShopOrderLogs::OPERATOR_TYPE_ORDER_MERGE,
                        'orderIds'      =>  $orderIds
                    ];
                }

                $this->updatePackageSkuStatus($newPackage);
            }
            return true;
        });
    }

    public function mergeAbleList()
    {
        $mergeAddress = PackageAddress::query()->with(['mergeAddress' => function ($query) {
            $query->whereHas('package', function ($query) {
                $query->where('split_merge_status', Package::PACKAGE_DEFAULT)
                    ->where('status', Package::STATUS_WAIT_DEAL)->whereIn('logistics_status', [Package::LOGISTICS_WAIT_APPLY, Package::LOGISTICS_APPLY_FAILURE]);
            });
        }, 'mergeAddress.package.packageAddress', 'mergeAddress.package.items.lineItem'])->whereHas('package', function ($query) {
            $query->where('split_merge_status', Package::PACKAGE_DEFAULT)
                ->where('status', Package::STATUS_WAIT_DEAL)->whereIn('logistics_status', [Package::LOGISTICS_WAIT_APPLY, Package::LOGISTICS_APPLY_FAILURE]);
        })->groupBy('unique_key')->selectRaw('unique_key, COUNT(*) as count' )->havingRaw('COUNT(*) > 1')->paginate($this->formData['size'] ?? 10);
        $mergePackageList = [];
        foreach ($mergeAddress as $address) {
            $mergePackage['unique_key'] = $address->unique_key;
            $packageList = [];
            foreach ($address->mergeAddress as $packageAddress) {
                $packageList[] = $packageAddress->package;
            }
            $mergePackage['package_list'] = $packageList;
            $mergePackageList[] = $mergePackage;
        }
        return ['data' => $mergePackageList, 'meta' => ['total' => $mergeAddress->total()]];
    }

    /**
     * 还原包裹合并
     */
    public function mergeRollback($params)
    {
        $packageIds = $params['ids'] ?? [];
        $packages = Package::query()->with('orders')->whereIn('id', $packageIds)->get();
        return DB::transaction(function () use ($packages) {
            $logData = [];
            foreach ($packages as $package) {
                if ($package->status != Package::STATUS_WAIT_DEAL) {
                    throw new AccidentException('只有待处理的包裹才能还原拆单', Code::OPERATE_FAIL);
                }
                $package->delete();
                $package->items()->delete();
                $package->orders->each(function ($order) use($package, &$logData) {
                    ShopOrderLogs::addLog([
                        'order_id' => $order->id,
                        'operator_type' => ShopOrderLogs::OPERATOR_TYPE_ORDER_MERGE,
                        'content' => '订单取消合包',
                    ]);

                    $logData = [
                        'id'  =>  $package->id,
                        'sn'  =>  $package->package_sn,
                        'order_id'  =>  $order->id,
                        'order_sn'  =>  $order->order_sn,
                        'old_status'  =>  $order->order_status,
                        'split_merge_status'  =>  $order->split_merge_status,
                    ];

                    $order->split_merge_status = Package::PACKAGE_DEFAULT;
                    $order->save();
                    $this->createByOrder($order);
                });
            }

            return true;
        });
    }

    /**
     * 包裹取消
     */
    public function cancel($package)
    {
        return DB::transaction(function () use ($package) {
            $package->status = Package::STATUS_CANCELED;
            $package->save();
            $outboundOrder = OutboundOrder::query()->where('package_id', $package->id)
                ->where('status', '!=', OutboundOrder::STATUS_CANCEL)->first();
            if (!empty($outboundOrder)) (new OutboundOrderService())->cancel($outboundOrder->id);
            // 记录操作日志
            return true;
        });
    }

    /**
     * @param $package
     * @return true|void
     * @throws Exception
     */
    public function outbound($package)
    {
        $sync_waybill_number = SystemConfigBaseService::getConfigValue('sync_waybill_number');
        if($sync_waybill_number == 3) {
            (new PackageBaseService($package))->packagePlatformDelivery();
        }
        $package->status = Package::STATUS_OUTBOUND;
        $package->save();
        if ($package->orders->isEmpty()) return true;
        $orderIds = $package->orders->pluck('id')->toArray();
        if (count($orderIds) > 0) {
            request()->offsetSet('ids', $orderIds);
            $orderService = new OrderService(new Order());
            $orderService->send();
        }
    }

    /** 同步订单地址变更
     * @param $order
     * @return void
     */
    public function syncOrderAddress($order)
    {
        // 先把合包的拆分
        foreach ($order->packages as $package) {
            if ($package->split_merge_status == Package::PACKAGE_MERGE) {
                $this->mergeRollback(['ids' => [$package->id]]);
            }
        }
        // 然后再关联包裹更新包裹地址
        $order->refresh();
        $order = Order::query()->with('packages')->find($order->id);
        foreach ($order->packages as $package) {
            $addressData = PackageAddress::init($package->id, $order->shippingAddress->toArray());
            PackageAddress::query()->where('package_id', $package->id)->update($addressData);
        }
    }

    /** 同步订单产品变更
     * @param $order
     * @return false|void
     */
    public function syncOrderGoods($order)
    {
        if (empty($order->packages) || count($order->packages) == 0) return false;
        $lineItems = OrderLineItem::with(['packageItems' => function ($query) {
            $query->whereHas('package', function ($query) {
                return $query->where('status', '!=', Package::STATUS_CANCELED);
            });
        }])->where('order_id', $order->id)->get();
        foreach ($lineItems as $item) {
            // 新增商品
            if (empty($item->packageItems) || count($item->packageItems) == 0) {
                PackageItem::query()->create([
                    'package_id' => $order->packages[0]->id,
                    'shop_order_id' => $order->id,
                    'shop_order_item_id' => $item->id,
                    'quantity' => $item->quantity
                ]);
            } else {
                $packageQuantity = $item->packageItems->sum('quantity');
                // 商品数量增加
                if ($item->quantity > $packageQuantity) {
                    $item->packageItems[0]->quantity += $item->quantity - $packageQuantity;
                    $item->packageItems[0]->save();
                }
                // 商品数量减少
                if ($item->quantity < $packageQuantity) {
                    $reduceQuantity = $packageQuantity - $item->quantity;
                    foreach ($item->packageItems as $packageItem) {
                        $itemReduceQuantity = min($packageItem->quantity, $reduceQuantity);
                        // 当前包裹item数量被扣完则删除包裹
                        if ($itemReduceQuantity == $packageItem->quantity) {
                            $packageItem->delete();
                        } else {
                            $packageItem->quantity -= $itemReduceQuantity;
                            $packageItem->save();
                        }
                        // 需要减少的数量等于当前扣除的数量，则结束
                        if ($itemReduceQuantity == $reduceQuantity) break;
                        $reduceQuantity -= $reduceQuantity;
                    }
                }
            }
        }
        // 删除商品
        foreach ($order->packages as $package) {
            $packageItems = PackageItem::with('lineItem')->where('package_id', $package->id)->get();
            foreach ($packageItems as $item) {
                if (empty($item->lineItem)) {
                    $item->delete();
                }
            }
        }
        // 如果包裹下的产品删完了，则连包裹一起删除
        foreach ($order->packages as $package) {
            $packageItems = PackageItem::query()->where('package_id', $package->id)->get();
            if ($packageItems->isEmpty()) {
                $package->delete();
                $package->items()->delete();
            }
        }
    }


    // 订单回滚最初状态删除对应包裹
    public function orderRollbackSync($order)
    {
        $order->packages->each(function ($package) {
            if ($package->status === Package::STATUS_OUTBOUND) {
                throw new AccidentException('当前订单包裹已出库不能操作回滚', Code::OPERATE_FAIL);
            }

            // 有出库单则向取消出库单
            if (!empty($package->outboundOrder)) {
                (new OutboundOrderService())->cancel($package->outboundOrder->id);
            }
            // 合并的包裹取消合并
            if ($package->split_merge_status == Package::PACKAGE_MERGE) {
                $this->mergeRollback(['ids' => [$package->id]]);
            }
        });

        // 删除订单对应包裹
        $order->refresh();
        $order->packages->each(function ($package) {
            $package->delete();
            $package->items()->delete();
            $package->packageAddress()->delete();
        });
    }

    public function packagePlatformDeliver($params)
    {
        validator($params, [
            'ids' => 'required|array'
        ])->validate();
        $packageList = Package::query()->whereIn('ids', $params['ids'])->get();
        $packageList->each(function ($package) {
            (new PackageBaseService($package))->packagePlatformDelivery();
        });
        return true;
    }


    /**
     * 更新包裹状态
     */
    protected function updatePackageSkuStatus($package)
    {
        $items = PackageItem::query()->where('package_id', $package->id)->get();
        $status = 0;
        if (count($items) === 1) {
            if ($items[0]->quantity > 1) $status = 1;
        } else {
            $status = 2;
        }
        $package->sku_status = $status;
        $package->save();
    }

}
