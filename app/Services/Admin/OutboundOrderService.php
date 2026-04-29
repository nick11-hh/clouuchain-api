<?php

namespace App\Services\Admin;


use App\Lib\Code;
use App\Models\GoodsSku;
use App\Models\InboundOrder;
use App\Models\InboundOrderItem;
use App\Models\Order;
use App\Models\OrderItemStock;
use App\Models\OutboundItemOrderStockRelate;
use App\Models\OutboundOrder;
use App\Models\OutboundOrderAddress;
use App\Models\OutboundOrderItem;
use App\Models\OutboundShopOrderRelate;
use App\Models\Stock;
use App\Models\StockChangeLogs;
use App\Models\StockItem;
use App\Models\StockLock;
use App\Models\SystemConfig;
use App\Models\WarehouseAddress;
use App\Services\Base\StockService;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class OutboundOrderService extends BaseService
{
    public $filterRules = [
        'custom_id'      => ['=', 'custom_id'],
        'warehouse_id'   => ['=', 'warehouse_id'],
        'created_at'     => ['between', ['begin_date', 'end_date']],
        'status'         => ['=', 'status']
    ];

    private OutboundOrderItem $itemModel;

    public function __construct()
    {
        $this->model = new OutboundOrder();
        $this->itemModel = new OutboundOrderItem();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function keyword()
    {
        if (isset($this->formData['keyword']) && !empty($this->formData['keyword'])) {
            switch ($this->formData['keyword_type']) {
                case 1:
                    $this->query->where('tracking_number', 'like', '%'.$this->formData['keyword'].'%');
                    break;
                case 2:
                    $this->query->whereHas('orders', function ($query) {
                        $query->where('order_id', 'like', '%'.$this->formData['keyword'].'%');
                    });
                    break;
                case 3:
                    $this->query->where('outbound_sn', 'like', '%'.$this->formData['keyword'].'%');
                    break;
                case 4:
                    $this->query->whereHas('items', function ($query) {
                        $query->where('sku', 'like', '%'.$this->formData['keyword'].'%');
                    });
                    break;
            }
        }
    }

    public function index()
    {
        $this->query->with(['items', 'warehouse', 'custom', 'address', 'orders']);

        $this->keyword();

        $this->query->latest();
        return parent::index();
    }


    public function show($id)
    {
        return $this->model::query()->with(['items', 'warehouse', 'custom', 'address'])->findOrFail($id);
    }

    /**
     * @param $params
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object
     * @throws Exception
     */
    public function scanData($params)
    {
        $scanSn = $params['scan_sn'] ?? '';
        $outbound = $this->model::query()->with(['items', 'warehouse', 'custom', 'address', 'orders'])
            ->where(function ($query) use ($scanSn) {
                return $query->where('outbound_sn', $scanSn)->orWhere('tracking_number', $scanSn);
            })->first();
        if (empty($outbound)) throw new AccidentException('出库单不存在', Code::OPERATE_FAIL);
        return $outbound;
    }

    public function statusCount()
    {
        //首次加载列表页时会传状态值，导致其他状态的统计数量都为0 所以暂时先过滤状态查询条件
        unset($this->filters['status']);
        $this->setFilter();

        $this->keyword();

        $statuses = $this->query->selectRaw('status, count(*) as count')->groupBy('status')->get();

        $statusCounts = $statuses->pluck('count', 'status');

        $allStatuses = array_keys($this->model::statusList()); //获取所有状态
        $statusCounts = collect($allStatuses)
            ->mapWithKeys(function ($status) use ($statuses) {
                // 如果状态存在于统计结果中，返回它的计数，否则返回0
                return [$status => $statuses->firstWhere('status', $status)?->count ?? 0];
            });

        $data = [];
        foreach ($statusCounts as $status => $count) {
            $data[] = ['status' => $status, 'count' => $count];
        }

        return $data;
    }


    /**
     * @param $params
     * @return mixed
     */
    public function store($params)
    {
        validator($params, $this->rules())->validate();
        info('创建出库单', $params);
        return DB::transaction(function () use ($params) {
            $data = $this->model::init($params);
            $outbound = $this->model::query()->create($data);

            $type = OutboundOrder::TYPE_SIGN_SKU;
            foreach ($params['items'] as $item) {
                $stock = Stock::query()->findOrFail($item['stock_id']);

                if ($stock->quantity < $item['quantity']) throw new AccidentException('库存不足', Code::OPERATE_FAIL);

                $itemData = $this->getOutboundItemByStock($stock);
                $itemData['quantity'] = $item['quantity'];
                $itemData = $this->itemModel::init($outbound->id, $itemData);
                if ($itemData['quantity'] > 1) $type = OutboundOrder::TYPE_SIGN_SKU_MULTIPLE;
                $outboundItem = $this->itemModel::query()->create($itemData);

                // 锁定库存
                $stockService = new StockService($stock);
                $lock = $stockService->setOperateId($outbound->id)->lockStock($outboundItem->quantity, StockLock::SOURCE_ORDER_WAIT_DELIVER);
                $stockService->autoLockStockItem($lock);
                // 保存锁定id
                $outboundItem->lock_id = $lock->id;
                $outboundItem->save();

                if (!empty($item['order_item_stock_ids'])) {
                    $this->createOrderStockRelate($outboundItem->id, $item['order_item_stock_ids']);
                }
            }

            if (count($params['items']) > 1) $type = OutboundOrder::TYPE_MULTIPLE_SKU;
            $outbound->type = $type;
            $outbound->save();

            if (!empty($params['shop_order_ids'])) {
                $this->createOutboundAndShopOrderRelate($outbound->id, $params['shop_order_ids']);
            }

            $addressData = OutboundOrderAddress::init($outbound->id, $params['address']);
            OutboundOrderAddress::query()->create($addressData);
            return $outbound;
        });
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     */
    public function update($id, $params)
    {
        validator($params, $this->rules())->validate();
        return DB::transaction(function () use ($id, $params) {

        });
    }

    /** 通过订单创建出库单
     * @param $package
     * @param $logisticInfo
     * @param $address
     * @return mixed
     */
    public function createByPacakge($package, $logisticInfo, $address)
    {
        $name = $address->name ?? '';
        $firstName = $address->first_name ?? '';
        $lastName = $address->last_name ?? '';
        $params = [
            'warehouse_id' => WarehouseAddress::query()->orderBy('custom_sort')->value('id'),
            'custom_id' => empty($package->orders[0]) ? 0 : ($package->orders[0]->customer_id ?? 0),
            'sale_platform' => $package->orders[0]->platform ?? 'shopify',
            'package_id' => $package->id,
            'logistics_provider' => (string)$package->express_companies_id,
            'tracking_number' => $logisticInfo->way_bill_number,
            'shipment_pdf' => $logisticInfo->label_url,
            'remark' => $package->remark ?? '',
            'items' => [],
            'shop_order_ids' => [],
            'address' => [
                'name' => $name ?: "{$firstName} {$lastName}",
                'first_name' => $firstName,
                'last_name' => $lastName,
                'address' => $address->address,
                'address2' => $address->address2 ?? '',
                'phone' => $address->phone,
                'city' => $address->city,
                'zip' => $address->zip,
                'province' => $address->province,
                'country' => $address->country,
                'company' => $address->company,
                'latitude' => $address->latitude,
                'longitude' => $address->longitude,
                'tax' => $address->tax,
            ]
        ];
        foreach ($package->items as $item) {
            $orderItemStockIds = OrderItemStock::query()->where('order_item_id', $item->shop_order_item_id)->get('id')->pluck('id')->toArray();
            $order = Order::query()->find($item->shop_order_id);
            $stock = Stock::query()->where('sku_id', $item->lineItem->mapping->goods_sku_id)
                ->where('custom_id', $order->use_customer_stock ? $order->customer_id: 0)->first();
            $params['items'][] = [
                'stock_id' => $stock->id,
                'goods_sku_id' => $item->lineItem->mapping->goods_sku_id,
                'stock' => $stock,
                'quantity' => $item->quantity,
                'order_item_stock_ids' => $orderItemStockIds
            ];
        }
        return $this->store($params);
    }


    /** 取消入库单
     * @param $id
     * @return bool
     */
    public function cancel($id)
    {
        $outboundOrder = $this->model::query()->findOrFail($id);
        $outboundOrder->status = OutboundOrder::STATUS_CANCEL;
        $outboundOrder->cancel_time = now();
        return $outboundOrder->save();
    }


    /**
     * @param $id
     * @param $params
     * @return bool|int
     */
    public function updatePackageInfo($id, $params)
    {
        $data = validator($params, $this->updatePackageInfoRules())->validate();

        $outboundOrder = $this->model::query()->findOrFail($id);

        throw_if(
            $outboundOrder->status === $this->model::STATUS_OUTBOUND,
            new AccidentException('已出库订单不能再更改重量尺寸', Code::OPERATE_FAIL)
        );

        return $outboundOrder->update($data);
    }

    /**
     * 订单出库
     * 1、波次拣货、开启称重：按照以前的逻辑
     * 2、波次拣货、关闭称重：完成二次分拣则自动称重跟完成出库单
     * 3、直接拣货、开启称重：隐藏“生成波次”按钮，显示“拣货”按钮；点击"拣货"则直接跳转到称重页面
     * 4、直接拣货、关闭称重：隐藏“生成波次”按钮，显示“拣货”按钮；自动称重跟完成出库单
     * @param $id
     * @return bool
     * @throws Exception|\Illuminate\Validation\ValidationException
     */
    public function outbound($id)
    {
        $outboundOrder = $this->model::query()->findOrFail($id);

        $getKeys = [
            SystemConfig::WAREHOUSE_PICKING_TYPE,
            SystemConfig::WAREHOUSE_WEIGHT,
        ];
        $configs = (new SystemConfigService())->getMultipleConfig($getKeys);

        //待出库
        $verifyStatus = OutboundOrder::STATUS_WAIT_OUTBOUND;

        //直接拣货
        if ($configs[SystemConfig::WAREHOUSE_PICKING_TYPE] === 2 && !empty($configs[SystemConfig::WAREHOUSE_WEIGHT])) $verifyStatus = OutboundOrder::STATUS_WAIT_PICKING;

        //校验状态
        if ($outboundOrder->status !== $verifyStatus) {
            throw new AccidentException('当前订单状态不能出库', Code::OPERATE_FAIL);
        }
        if ($outboundOrder->abnormal_status === 1) throw new AccidentException('当前订单处于异常状态，不能出库', Code::OPERATE_FAIL);

        return DB::transaction(function () use ($outboundOrder) {
            $outboundOrder->status = OutboundOrder::STATUS_OUTBOUND;
            $outboundOrder->outbound_time = now();

            // 释放锁定的库存并且释放
            $outboundOrder->items->each(function ($item) use ($outboundOrder) {
                if ($item->stockLock) {
                    (new StockService())->setOperateSn($outboundOrder->outbound_sn)
                        ->unlockStockLockAndConsume($item->stockLock, StockChangeLogs::SOURCE_ORDER_DEDUCTION);
                }
            });

            $packageService = new PackageService();
            $packageService->outbound($outboundOrder->package);

            return $outboundOrder->save();
        });
    }

    /**
     * @return bool
     */
    public function weighingCompleted(): bool
    {
        validator($this->formData, [
            'outbound_id' => 'required|int',
            'weight' => 'required|numeric',
            'long' => 'sometimes|nullable|numeric',
            'width' => 'sometimes|nullable|numeric',
            'height' => 'sometimes|nullable|numeric',
            ])->validate();

        $id = $this->formData['outbound_id'];
        $outboundOrder = $this->model::query()->with(['orders'])->findOrFail($id);

        throw_if(
            $outboundOrder->status === $this->model::STATUS_OUTBOUND,
            new AccidentException('已出库订单不能再更改重量尺寸', Code::OPERATE_FAIL)
        );

        return DB::transaction(function () use ($outboundOrder) {
            //更新出库单尺寸重量及状态
            $outboundOrder->long = $this->formData['long'] ?? 0;
            $outboundOrder->width = $this->formData['width'] ?? 0;
            $outboundOrder->height = $this->formData['height'] ?? 0;
            $outboundOrder->weight = $this->formData['weight'] ?? 0;
            $outboundOrder->status = OutboundOrder::STATUS_OUTBOUND;
            $outboundOrder->outbound_time = now();
            $this->outbound($outboundOrder->id);

            return $outboundOrder->save();
        });
    }


    /** 数据转换
     * @param $item
     * @return mixed
     */
    protected function getOutboundItemByStock($stock)
    {
        $item['stock_id'] = $stock->id;
        $item['goods_name'] = $stock->goods_name ?? '';
        $item['spec_name'] = $stock->spec_name;
        $item['sku_image'] = $stock->sku_image;
        $item['sku'] = $stock->sku;
        return $item;
    }

    /**
     * @param $outboundItemId
     * @param $orderItemStockIds
     * @return bool
     */
    protected function createOrderStockRelate($outboundItemId, $orderItemStockIds)
    {
        $data = [];
        foreach ($orderItemStockIds as $id) {
            $data[] = [
                'outbound_item_id' => $outboundItemId,
                'order_item_stock_id' => $id,
            ];
        }
        return OutboundItemOrderStockRelate::query()->insert($data);
    }

    /**
     * @param $outboundId
     * @param $shopOrderIds
     * @return bool
     */
    protected function createOutboundAndShopOrderRelate($outboundId, $shopOrderIds)
    {
        $data = [];
        foreach ($shopOrderIds as $id) {
            $data[] = [
                'outbound_order_id' => $outboundId,
                'shop_order_id' => $id,
            ];
        }
        return OutboundShopOrderRelate::query()->insert($data);
    }

    protected function rules()
    {
        return [
            'custom_id' => 'required|int',
            'warehouse_id' => 'required|int',
            'sale_platform' => 'required|string',
            'logistics_provider' => 'required|string',
            'tracking_number' => 'required|string',
            'shipment_pdf' => 'sometimes|string',
            'remark' => 'sometimes',
            'items' => 'required|array',
            'items.*.stock_id' => 'required|int',
            'items.*.quantity' => 'required|int',
            'address' => 'required|array',
            'address.name' => 'required|string'
        ];
    }

    protected function updatePackageInfoRules()
    {
        return [
            'weight' => 'required|numeric',
            'long' => 'sometimes|nullable|numeric',
            'width' => 'sometimes|nullable|numeric',
            'height' => 'sometimes|nullable|numeric',
        ];
    }

}


