<?php

namespace App\Services\Admin;

use App\Jobs\FulfillmentOrderJob;
use App\Jobs\PurchaseToInboundOrderJob;
use App\Lib\Code;
use App\Models\GoodsSku;
use App\Models\GoodsSupplier;
use App\Models\Order;
use App\Models\Order as OrderModel;
use App\Models\PlanPurchaseRelationModel;
use App\Models\PlatformSkuMapping;
use App\Models\OrderItemPurchase;
use App\Models\PurchaseOrderLogs;
use App\Models\PurchaseOrdersItemsModel;
use App\Models\PurchaseOrdersModel;
use App\Models\PurchasePlan;
use App\Models\PurchasePlanItem;
use App\Models\StockChangeLogs;
use App\Models\StockLockLog;
use App\Models\SystemConfig;
use App\Models\WarehouseAddress;
use App\Models\WarehouseGoodsAllocation;
use App\Services\Base\OrderBaseService;
use App\Services\Base\StockService;
use App\Services\Base\SystemConfigService;
use App\Services\Base\SystemConfigService as SystemConfigBaseService;
use App\Services\Collect\Platform\Y1688\Y1688Service;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;
use App\Exceptions\AccidentException;

class PurchaseOrderService extends BaseService
{
    public $filterRules = [
        'shop_id' => ['=', 'shop_id'],
        'status' => ['=', 'status'],
        'skus:title'   => ['like', 'title'],
        'provider_id' => ['=', 'supplier_id'],
        'purchase_user_id' => ['=', 'user_id'],
        'supplier:type' => ['=', 'suppliers_type'],
        'created_at' => ['between', ['begin_date', 'end_date']]
    ];

    public $orderBy = [
        'created_at' => 'desc'
    ];
    public function __construct(PurchaseOrdersModel $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with(['skus', 'shop:id,shop_name,status', 'shopOrder:id,order_id', 'warehouse', 'supplier', 'purchasePlan.order']);
        if ($this->formData['type'] ?? '') {
            $this->query->whereHas('supplier', function ($query) {
                $query->where('type', $this->formData['type']);
            });
        }

        if(isset($this->formData['keyword']) && !empty($this->formData['keyword'])) {
            switch ($this->formData['keyword_type']) {
                case 1:
                    $this->query->where('order_sn', 'like', '%'.$this->formData['keyword'].'%');//采购单号
                    break;
                case 2:
                    $this->query->whereHas('skus', function ($query) {
                        $query->where('sku', 'like', '%'.$this->formData['keyword'].'%');//sku
                    });
                    break;
                case 3:
                    $this->query->whereHas('purchasePlan', function ($query) {
                        $query->where('plan_sn', 'like', '%'.$this->formData['keyword'].'%');//采购计划
                    });
                    break;
                case 4:
                    $this->query->whereHas('purchasePlan.order', function ($query) {
                        $query->where('dsp_shop_order.order_id', 'like', '%'.$this->formData['keyword'].'%');//系统单号
                    });
                    break;
                case 5:
                    $this->query->where('shipment_number', 'like', '%'.$this->formData['keyword'].'%');//物流单号
                    break;
            }
        }

        $res = parent::index();

        $res->each(function($item) {
            $relatedOrders = [];
            $item->purchasePlan->each(function($plan) use(&$relatedOrders) {
                if($plan->order) {
                    $relatedOrders = array_merge($relatedOrders, array_column($plan->order->toArray(), 'order_id'));
                }
            });

            $plans = $item->purchasePlan->pluck('plan_sn')->toArray();

            $item->related_orders = count($relatedOrders) > 0 ? implode(',', array_unique($relatedOrders)) : '';
            $item->plans = count($plans) > 0 ? implode(',', array_unique($plans)) : '';
        });

        return $res;
    }

    public function show($id)
    {
        return $this->model::query()->with(['skus', 'warehouse'])->findOrFail($id);
    }

    public function getScanData()
    {
        validator($this->formData, [
            'keyword' => 'required'
        ], [], [
            'keyword' => '采购单号/物流单号'
        ])->validate();


        $query = $this->model::query()->with(['skus', 'warehouse'])
            ->where(function ($query) {
                $query->where('order_sn', 'like', '%'.$this->formData['keyword'].'%')->orWhere('shipment_number', 'like', '%'.$this->formData['keyword'].'%');
            })->where('status',  3);
        $data = $query->first();

        if(empty($data)) {
            throw new AccidentException('操作失败，只能操作待入库的采购单', Code::OPERATE_FAIL);
        }

        $data->quantity = $data->skus->sum('quantity');
        $data->inbound_quantity = $data->skus->sum('inbound_quantity');
        return $data;
    }

    /** 创建采购单
     * @param $params
     * @return mixed
     */
    public function createPurchase($params): mixed
    {
        validator($params, [
            'order_info' => 'required',
            'order_items.*.skus' => 'required',
        ])->validate();

        DB::beginTransaction();
        try {
            foreach($params['order_items'] as $item) {
                $params['order_info']['warehouse_id'] = $item['warehouse']['id'];
                $params['order_info']['supplier_id'] = $item['id'];
                $purchaseData = $this->model::init($params['order_info'], isset($params['id']));

                if(isset($params['id'])) {
                    $purchaseOrder = $this->model::where('id', $params['id'])->first();
                    if($purchaseOrder) {
                        $this->model::where('id', $params['id'])->update($purchaseData);
                        PurchaseOrdersItemsModel::where('purchase_order_id', $params['id'])->delete();

                        //添加日志
                        $logData = [
                            'purchase_id' => $purchaseOrder->id,
                            'operator_type' => PurchaseOrderLogs::OPERATOR_TYPE_CREATE,
                            'content' => '编辑采购订单',
                        ];
                    }
                } else {
                    $purchaseOrder = $this->model::create($purchaseData);

                    //添加日志
                    $logData = [
                        'purchase_id' => $purchaseOrder->id,
                        'operator_type' => PurchaseOrderLogs::OPERATOR_TYPE_CREATE,
                        'content' => '新增采购订单',
                    ];
                }

                $itemData = [];
                foreach($item['skus'] as $sku) {
                    $goodsSupplier = GoodsSupplier::where(['goods_sku_id'=> $sku['goods_sku_id'], 'supplier_id' => $item['id']])->first();
                    $sku['offerId'] = $goodsSupplier->purchase_goods_id;
                    $sku['specId'] = $goodsSupplier->purchase_spec_id;
                    $itemData[] = PurchaseOrdersItemsModel::init($purchaseOrder->id, $sku);

                    // 生成采购单后，采购计划item表中增加已采购数量
                    PurchasePlanItem::where('id', $sku['plan_item_id'])->increment('purchased', $sku['plan_qty']);
                    PurchasePlanItem::where('id', $sku['plan_item_id'])->update(['supplier_id' => $item['id'], 'warehouse_id' => $item['warehouse']['id']]);
                    $planItem = PurchasePlanItem::where('id', $sku['plan_item_id'])->select('plan_qty', 'purchased')->first();
                    if($planItem && $planItem->purchased >= $planItem->plan_qty) {
                        PurchasePlan::where('id', $sku['plan_id'])->update(['status' => 2]);
                    }
                }

                $planIds = array_column($item['skus'], 'plan_id');
                $planIds = array_unique($planIds);
                $relationData = [];
                foreach($planIds as $id) {
                    $relationData[] = [
                        'plan_id' => $id,
                        'purchase_id' => $purchaseOrder->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }

                PurchaseOrderLogs::addLog($logData);

                PlanPurchaseRelationModel::insert($relationData);

                PurchaseOrdersItemsModel::insert($itemData);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            info('创建采购单失败', [
                'msg' => $e->getMessage(),
                'file'=> $e->getFile(),
                'line'=> $e->getLine()
            ]);
            throw new AccidentException('创建采购单失败', Code::OPERATE_FAIL);
        }

        return true;
    }

    public function createPurchaseBackups($params): mixed
    {
        validator($params, $this->rules())->validate();
        return DB::transaction(function () use ($params) {
            # todo 目前使用第一个仓库，后续需要确认创建采购单的仓库
            if (empty($params['warehouse_id'])) {
                $params['warehouse_id'] = WarehouseAddress::query()->first('id')->id;
            }

            // 需要采购的商品通过供应商分组生成多个采购单
            $goodsGroupList = $this->goodsGroupBySupplier($params['goods']);
            foreach ($goodsGroupList as $key => $group) {
                $params['supplier_id'] = $key;
                $params['platform'] = $group[0]['goods_info']->goods->purchase_platform ?? '';
                $data = PurchaseOrdersModel::init($params);
                $purchaseOrder = $this->model::query()->create($data);
                foreach ($group as $value) {
                    $goods = $value['goods_info'];
                    $itemData = [
                        'url' => $goods->goods->purchase_url ?? '',
                        'platform' => $params['platform'],
                        'imgs' => $goods->images,
                        'variant_title' => $goods->spec_name,
                        'title' => $goods->goods->goods_name,
                        'quantity' => $value['quantity'],
                        'sku' => $goods->sku_id,
                        'sku_id' => $goods->id,
                        'goods_id' => $goods->goods_id,
                        'purchase_price' => $value['goods_supplier']->price,
                        'offerId' => $value['goods_supplier']->purchase_goods_id,
                        'specId' => $value['goods_supplier']->purchase_spec_id,
                    ];

                    if (!empty($value['order_items'])) {
                        $itemData['order_sn'] = array_column($value['order_items'], 'order_sn');
                    }

                    $items = PurchaseOrdersItemsModel::init($purchaseOrder->id, $itemData);
                    $purchaseItem = PurchaseOrdersItemsModel::query()->create($items);

                    if (!empty($value['order_items'])) {  // 创建采购单关联的订单数据
                        $this->createOrderItemPurchase($value['order_items'], $purchaseItem, $value['quantity']);
                    }
                }
            }
            return true;
        });
    }

    /** 商品通过供应商分组
     * @param $goodsParams
     * @return array
     * @throws Exception
     */
    public function goodsGroupBySupplier($goodsParams)
    {
        $result = [];
        foreach ($goodsParams as $goods) {
            $goodsSku = GoodsSku::query()->with(['goods', 'goodsSuppliers' => function ($query) {
                return $query->orderBy('is_default', 'desc');
            }, 'goodsSuppliers.supplier'])->where('id', $goods['sku_id'])->first();

            if (empty($goodsSku)) throw new AccidentException('采购商品不存在', Code::OPERATE_FAIL);
            if ($goodsSku->goodsSuppliers->isEmpty()) throw new AccidentException('商品未设置供货关系', Code::OPERATE_FAIL);

            $goodsSupplier = $goodsSku->goodsSuppliers[0];
            $goods['goods_info'] = $goodsSku;
            $goods['goods_supplier'] = $goodsSupplier;

            if (isset($result[$goodsSupplier->supplier_id])) {
                $result[$goodsSupplier->supplier_id][] = $goods;
            } else {
                $result[$goodsSupplier->supplier_id] = [$goods];
            }
        }
        return $result;
    }

    /** 创建订单采购单发货项
     * @param $orderItems
     * @param $purchaseItem
     * @param $quantity
     * @return void
     */
    public function createOrderItemPurchase($orderItems, $purchaseItem, $quantity)
    {
        $sourceData = [];
        foreach ($orderItems as $item) {
            $sourceData[] = [
                'purchase_id' => $purchaseItem->purchase_order_id,
                'purchase_item_id' => $purchaseItem->id,
                'order_id' => $item['order_id'],
                'order_item_id' => $item['order_item_id'],
                'quantity' => $quantity,
                'lock_id' => 0,

            ];
        }
        OrderItemPurchase::query()->insert($sourceData);
    }


    /**
     * 标记订单为已采购/取消
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     * @throws Throwable
     */
    public function markOrder()
    {
        validator($this->formData, [
            'ids' => 'required',
            'status' => [
                'required',
                Rule::in([
                    PurchaseOrdersModel::STATUS_PENDING,
                    PurchaseOrdersModel::STATUS_PURCHASED,
                    PurchaseOrdersModel::STATUS_WAIT_STORAGE,
                    PurchaseOrdersModel::STATUS_CANCELLED,
                    PurchaseOrdersModel::STATUS_CANCEL,
                         ])
            ]
        ], [], [
            'ids' => '采购单id',
            'status' => '状态'
        ])->validate();

        // throw_if(
        //     $this->model::whereIn('id', $this->formData['ids'])->where('status', '<>', PurchaseOrdersModel::STATUS_PENDING)->first(),
        //     new AccidentException('操作失败，操作的采购单只能是待处理状态订单', Code::OPERATE_FAIL)
        // );
        $status = $this->formData['status'];
        $statusName = $this->model::getStatusName($status);

        $list = $this->model::query()->whereIn('id', $this->formData['ids'])->get();
        $list->each(function ($item) use ($status, $statusName) {
            $logContent = "采购状态变更：{$item->status_name} => {$statusName}";

            //添加日志
            $logData = [
                'purchase_id' => $item->id,
                'operator_type' => PurchaseOrderLogs::OPERATOR_TYPE_STATUS_CHANGE,
                'content' => $logContent,
            ];
            PurchaseOrderLogs::addLog($logData);

            $item->update(['status' => $status]);
        });

        //设为待入库
        if ($this->formData['status'] === PurchaseOrdersModel::STATUS_WAIT_STORAGE ) {
            $fast = SystemConfigService::getConfigValue(SystemConfig::FAST_IN_FAST_OUT);

            // 关闭快进快出 生成入库单
            if (empty($fast)) {
                dispatch(new PurchaseToInboundOrderJob($this->formData['ids']));
            }
        }

        return true;
    }

    /**
     * 设置采购商品链接
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     */
    public function setUrl()
    {
        validator($this->formData, [
            'ids' => 'required',
            'url' => 'required'
        ], [], [
            'ids' => '采购商品id',
            'url' => '采购链接'
        ])->validate();

        return PurchaseOrdersItemsModel::whereIn('id', $this->formData['ids'])->update(['platform_url' => $this->formData['url']]);
    }

    /**
     * 设置物流信息
     * @return void
     */
    public function setShipmentInfo():bool
    {
        validator($this->formData, ['ids' => 'required'], [], ['ids' => '订单id'])->validate();

        $list = $this->model::query()->whereIn('id', $this->formData['ids'])->get();
        $list->each(function ($item) {
            $content = "设置物流信息，";

            $data = [];
            if(isset($this->formData['platform_sn']) && !empty($this->formData['platform_sn'])) {
                $data['platform_sn'] = $this->formData['platform_sn'];

                $content .= "采购平台订单号：{$item->platform_sn} => {$data['platform_sn']} ";
            }

            // 设置物流单号
            if(isset($this->formData['shipment_number']) && !empty($this->formData['shipment_number'])) {
                $data['shipment_number'] = $this->formData['shipment_number'];

                $content .= "物流单号：{$item->shipment_number} => {$data['shipment_number']} ";
            }

            if(count($data)) {
                //添加日志
                $logData = [
                    'purchase_id' => $item->id,
                    'operator_type' => PurchaseOrderLogs::OPERATOR_TYPE_STATUS_CHANGE,
                    'content' => $content,
                ];
                PurchaseOrderLogs::addLog($logData);

                $item->update($data);
            }

        });

        return true;
    }

    /**
     * 状态统计
     * @return array
     */
    public function statusCount()
    {
        //首次加载列表页时会传状态值，导致其他状态的统计数量都为0 所以暂时先过滤状态查询条件
        unset($this->filters['status']);
        $this->setFilter();

        if ($this->formData['type'] ?? '') {
            $this->query->whereHas('supplier', function ($query) {
                $query->where('type', $this->formData['type']);
            });
        }

        if(isset($this->formData['keyword']) && !empty($this->formData['keyword'])) {
            switch ($this->formData['keyword_type']) {
                case 1:
                    $this->query->where('order_sn', 'like', '%'.$this->formData['keyword'].'%');//采购单号
                    break;
                case 2:
                    $this->query->whereHas('skus', function ($query) {
                        $query->where('sku', 'like', '%'.$this->formData['keyword'].'%');//sku
                    });
                    break;
                case 3:
                    $this->query->whereHas('purchasePlan', function ($query) {
                        $query->where('plan_sn', 'like', '%'.$this->formData['keyword'].'%');//采购计划
                    });
                    break;
                case 4:
                    $this->query->whereHas('purchasePlan.order', function ($query) {
                        $query->where('dsp_shop_order.order_id', 'like', '%'.$this->formData['keyword'].'%');//系统单号
                    });
                    break;
                case 5:
                    $this->query->where('shipment_number', 'like', '%'.$this->formData['keyword'].'%');//物流单号
                    break;
            }
        }

        $counts = $this->query->select('status', DB::raw('count(*) as count'))->groupBy('status')->get();

        $data = [];
        $status = array_keys(PurchaseOrdersModel::statusList());
        $counts->each(function($item) use(&$data){
            $data[$item->status] = $item->count;
        });

        foreach($status as $item) {
            if(!isset($data[$item])) {
                $data[$item] = 0;
            }
        }

        ksort($data);

        return $data;
    }

    /**
     * 确认收货
     * @return void
     * @throws Throwable
     */
    public function confirm()
    {
        validator($this->formData, ['keyword' => 'required'], [], ['keyword' => '采购单号/物流单号'])->validate();

        throw_if(
            $this->model::where(function($query) {
                $query->where('order_sn', 'like', '%'.$this->formData['keyword'].'%')->orWhere('shipment_number', 'like', '%'.$this->formData['keyword'].'%');
            })->where('status', '<>', PurchaseOrdersModel::STATUS_WAIT_STORAGE)->first(),
            new AccidentException('操作失败，只有待入库订单才能签收', Code::OPERATE_FAIL)
        );

        return $this->model::where('order_sn', 'like', '%'.$this->formData['keyword'].'%')
            ->orWhere('shipment_number', 'like', '%'.$this->formData['keyword'].'%')
            ->update(['status' => PurchaseOrdersModel::STATUS_IN_STOCK]);
    }

    /**
     * 商品撞库
     * @param $sku_id
     * @param $sku_code
     * @return array
     */
    public function getProduct($sku_id, $sku_code): array
    {
        $mapping = PlatformSkuMapping::where(function($query) use ($sku_id, $sku_code) {
            $query->where('platform_sku_id', $sku_id)->orWhere('custom_sku_id', $sku_code);
        })->first();

        if(empty($mapping)) {
            return [];
        }

        return [
            'platform' => $mapping->purchase_platform,
            'url'      => $mapping->purchase_url,
            'offerId'  => $mapping->purchase_product_id,
            'specId'   => $mapping->purchase_spec_id,
        ];
    }

    /**
     * 自动采购
     * @param $id // 采购单id
     * @return bool
     * @throws Exception
     */
    public function autoPurchase($id): bool
    {
        $orderItems = PurchaseOrdersItemsModel::where('purchase_order_id', $id)->get();

        $goods = [];
        $orderItems->each(function ($item) use(&$goods, $id) {

            if(!$item->offerId || !$item->specId) {
                throw new AccidentException('商品id或规格id不能为空', Code::OPERATE_FAIL);
            }

            $goods[] = [
                'offerId'  => $item->offerId,
                'specId'   => $item->specId,
                'quantity' => $item->quantity,
            ];
        });

        $alibaba = new Y1688Service();

        DB::beginTransaction();
        try {
            $this->model::where('id', $id)->update(['order_time'=> now()]);
            $alibaba->createCrossOrder($id, $goods);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            //1688下单失败-记录日志、将采购订单设为异常
            $logContent = "1688批量下单失败：{$e->getMessage()}";

            $logData = [
                'purchase_id' => $id,
                'operator_type' => PurchaseOrderLogs::OPERATOR_TYPE_1688_ORDER,
                'content' => $logContent,
            ];
            PurchaseOrderLogs::addLog($logData);

            PurchaseOrdersModel::where('id', $id)->update(['status' => PurchaseOrdersModel::STATUS_CANCELLED]);

            logger('1688批量下单失败', [$e->getMessage(), $e->getLine(), $e->getFile()]);
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * 同步1688订单物流信息
     * @param $ids
     * @return bool
     * @throws Exception
     */
    public function syncPurchaseOrdersStatus($ids): bool
    {
        $orderItems = PurchaseOrdersModel::whereIn('id', $ids)->get();

        $alibaba = new Y1688Service();

        try {
            foreach ($orderItems as $orderItem) {
                $alibaba->syncOrdersStatus($orderItem);
            }
            info('1688采购订单物流信息同步成功');
            return true;
        } catch (Exception $e) {
            logger($e->getMessage());
            logger($e->getLine());
            logger($e->getFile());
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    /** 采购单余货入库
     * @param $id
     * @param $params
     * @return mixed
     */
    public function purchaseStockInStorage($id, $params): mixed
    {
        validator($params, $this->inStorageRules())->validate();

        $purchase = $this->model::query()->findOrFail($id);

        if($purchase->status !== 3) {
            throw new AccidentException('操作失败，只能操作待入库状态采购单', Code::OPERATE_FAIL);
        }

        return DB::transaction(function () use ($id, $params, $purchase) {
            $purchaseItems = PurchaseOrdersItemsModel::query()->with('orderItemPurchase')->where('purchase_order_id', $id)
                ->whereIn('id', array_column($params['items'], 'id'))->get();
            $inStorageParams = collect($params['items'])->keyBy('id');
            $stockService = new StockService();
            $isFinish = true;
            foreach ($purchaseItems as $item) {
                $inStorageItem = $inStorageParams[$item->id] ?? null;

                if (empty($inStorageItem)) continue;

                if (empty($inStorageItem['quantity'])) continue;

                if ($inStorageItem['quantity'] > $item->quantity - $item->send_quantity) {
                    throw new AccidentException('入库数量超过采购数量', Code::OPERATE_FAIL);
                }
                $location = WarehouseGoodsAllocation::query()->where('code', $inStorageItem['location_code'])->first();
                if (empty($location)) throw new AccidentException('库位不存在', Code::OPERATE_FAIL);
                $item->receive_quantity += $inStorageItem['quantity'];//到货数量
                $item->inbound_quantity += $inStorageItem['quantity'];//入库数量

                // 判断到货数量是否等于采购数量
                if($item->receive_quantity < $item->quantity) {
                    $isFinish = false;
                }
                $item->save();
                $stockItem = $stockService->purchaseInStorage($purchase, $item, $location);
                # todo 异常入库数量处理
                // 关联了订单的库存锁定
                $item->orderItemPurchase->each(function ($item) use ($stockService, $stockItem) {
                    if ($item->status != 0) return false;
                    $lock = $stockService->lockStock($item->quantity, $stockItem, StockLockLog::SOURCE_ORDER_WAIT_DELIVER);
                    $item->status = 1;
                    $item->lock_id = $lock->id;
                    $item->save();
                });
            }

            if($isFinish) {
                $purchase->status = PurchaseOrdersModel::STATUS_IN_STOCK;
                $purchase->save();
            }

            return true;
        });
    }

    public function getMatchOrderList($id)
    {
        // 采购单关联的订单
        $purchaseOrder = PurchaseOrdersModel::query()->with('purchasePlan.order')->findOrFail($id);
        $orderIds = [];
        $purchaseOrder->purchasePlan->each(function($plan) use(&$orderIds){
            if($plan->order) {
                $plan->order->each(function ($order) use(&$orderIds){
                    $orderIds[] = $order->pivot['order_id'];
                });
            }
        });

        //订单信息及关联的采购信息
        $orderList = Order::query()->with(['lineItems.mapping.goodsSku.goods', 'purchasePlan.purchase.skus'])->whereIn('id', $orderIds)->get();

        // 判断是否到齐
        $orderList->each(function ($order) use ($id) {
            //标记 1-齐货 0-缺货
            $arrived = 1;

            //订单产品对应的购买数量
            $skuQuantity = $order->lineItems->pluck('quantity', 'goods_sku_id')->toArray();

            //循环订单关联的采购计划
            $order->purchasePlan->each(function ($plan) use ($id, &$arrived, $skuQuantity) {
                //循环采购计划关联的采购订单
                $plan->purchase->each(function($purchase) use ($id, &$arrived, $skuQuantity) {
                    //循环采购订单的产品
                    $purchase->skus->each(function ($sku) use ($id, &$arrived, $skuQuantity) {
                        if (isset($skuQuantity[$sku->sku_id])) {
                            $sendQuantity = $skuQuantity[$sku->sku_id];//订单数量

                            //订单关联的采购单等于当前的采购订单时，根据采购数量做判断 ，否则根据到货数量做判断
                            if ($sku->purchase_order_id === (int)$id) {
                                //采购数量小于购买数量时则为缺货
                                if ($sku->quantity < $sendQuantity) {
                                    $arrived = 0;
                                }
                            } else {
                                //非当前采购单 根据到货数量做判断条件
                                if ($sku->receive_quantity < $sendQuantity) {
                                    $arrived = 0;
                                }
                            }
                        }
                    });
                });
            });

            $order->arrived = $arrived;
        });
        return $orderList;
    }

    /**
     * @param $id
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getMatchOrderListOld($id)
    {
        // 采购单关联的订单
        // $purchaseOrders = PurchaseOrdersModel::query()->with(['skus', 'skus.shopOrders'])->findOrFail($id);
        // $orders = $purchaseOrders->skus->pluck('shopOrders')->toArray();
        // $orders = array_merge(...$orders);
        // $orderIds = array_column($orders, 'id');

        $purchasePlans = PurchaseOrdersModel::query()->with('purchasePlan.order')->findOrFail($id);
        $orderIds = [];
        $purchasePlans->purchasePlan->each(function($plan) use(&$orderIds){
            if($plan->order) {
                $plan->order->each(function ($order) use(&$orderIds){
                    $orderIds[] = $order->pivot['order_id'];
                });
            }
        });

        // 获取订单列表
        $orderList = Order::query()->with(['lineItems', 'lineItems.mapping.goodsSku.goods', 'lineItems.purchaseItems.purchaseItem.order', 'lineItems.purchaseItems.stockLock.stock'])
            ->with(['lineItems.stockItems.stockItem.stock'])->whereIn('id', $orderIds)->get();

        // 判断是否到齐
        $orderList->each(function ($order) use ($id) {
            $order->arrived = 1;
            $order->lineItems->each(function ($item) use (&$order, $id) {
                if (!$item->purchaseItems->isEmpty()) {
                    foreach ($item->purchaseItems as $purchaseItem) {
                        if ($purchaseItem->purchase_id != $id && $purchaseItem->status === 0) {
                            $order->arrived = 0;
                        }
                    }
                }

            });
        });
        return $orderList;
    }


    public function orderDeliverByPurchase($params)
    {
        validator($params, $this->orderDeliverRules())->validate();

        //采购单及采购信息
        $purchaseOrder = PurchaseOrdersModel::query()->select(['id'])->with('skus')->findOrFail($params['purchase_id']);

        //订单信息及关联的采购信息
        $order = Order::query()->with(['lineItems', 'purchasePlan.purchase:id'])->findOrFail($params['order_id']);

        $statusList = [OrderModel::STATUS_WAIT_PRINT_OUT_STOCK, OrderModel::STATUS_WAIT_PRINT_IN_STOCK];
        if (!in_array($order->order_status, $statusList)) {
            throw new AccidentException('订单状态不允许发货', Code::OPERATE_FAIL);
        }

        if ($order->is_disable === Order::IS_DISABLE_YES) {
            throw new AccidentException('该订单已禁止处理，不允许发货', Code::OPERATE_FAIL);
        }

        return DB::transaction(function () use ($params, $order, $purchaseOrder) {

            //订单产品对应的购买数量
            $skuQuantity = $order->lineItems->pluck('quantity', 'goods_sku_id')->toArray();

            //循环订单关联的采购单信息
            $order->purchasePlan->each(function ($plan) use ($purchaseOrder, $skuQuantity) {
                $plan->purchase->each(function($purchase) use ($purchaseOrder, $skuQuantity) {
                    if ($purchase->id === $purchaseOrder->id) {
                        //更新采购单发货数量
                        $purchaseOrder->skus->each(function ($sku) use ($skuQuantity) {
                            if (isset($skuQuantity[$sku->sku_id])) {
                                $sendQuantity = $skuQuantity[$sku->sku_id];
                                $sku->receive_quantity += $sendQuantity;//到货数量
                                $sku->send_quantity += $sendQuantity;//发货数量
                                $sku->save();
                            }
                        });
                    }
                });
            });

            //更新采购订单状态为已完成 发货数量 +入库数量 >= 购买数量
            $purchaseOrder = PurchaseOrdersModel::query()->select(['id', 'status'])->with('skus')->findOrFail($params['purchase_id']);
            $totalQuantity = $purchaseOrder->skus->sum('quantity');//购买数量
            //操作数量 = 发货数量 + 入库数量
            $operationsQuantity = $purchaseOrder->skus->sum('send_quantity') + $purchaseOrder->skus->sum('inbound_quantity');
            if ($operationsQuantity >= $totalQuantity) {
                $purchaseOrder->status = PurchaseOrdersModel::STATUS_IN_STOCK;
                $purchaseOrder->save();
            }

            $order->order_status = OrderModel::STATUS_SHIPPED;
            $order->save();

            //处理订单包材
            if (isset($params['packing_materials'])) {
                $orderService = new OrderService(new Order());
                $orderService->orderPackingMaterials($params);
            }

            // 消耗库存
            $order->lineItems->each(function ($item) use ($order) {
                $item->stockItems->each(function ($stockItem) use ($order) {
                    $stockService = new StockService();
                    $stockService->setOperateSn($order->order_id)
                                 ->unlockAndConsume($stockItem->lock_id, StockLockLog::SOURCE_ORDER_DELIVERED, StockChangeLogs::SOURCE_ORDER_DEDUCTION);
                });
            });

            return true;
        });
    }

    /**
     * 旧版 确认发货流程
     * @param $params
     * @return mixed
     */
    public function orderDeliverByPurchaseOld($params)
    {
        validator($params, $this->orderDeliverRules())->validate();

        $purchaseOrder = PurchaseOrdersModel::query()->findOrFail($params['purchase_id']);

        $order = Order::query()->with(['lineItems', 'lineItems.purchaseItems.purchaseItem.order'])
            ->with(['lineItems.stockItems.stockItem.stock'])->findOrFail($params['order_id']);

        $statusList = [OrderModel::STATUS_WAIT_PRINT_OUT_STOCK, OrderModel::STATUS_WAIT_PRINT_IN_STOCK];
        if (!in_array($order->order_status, $statusList)) {
            throw new AccidentException('订单状态不允许发货', Code::OPERATE_FAIL);
        }

        return DB::transaction(function () use ($params, $order, $purchaseOrder) {

            $order->lineItems->each(function ($item) use ($order, $purchaseOrder) {
//                if ($item->purchaseItems->isEmpty()) throw new AccidentException('未找到对应采购单', Code::OPERATE_FAIL);
                $item->purchaseItems->each(function ($purchaseItem) use ($order, $purchaseOrder)  {
                    if ($purchaseItem->purchase_id === $purchaseOrder->id) {  // 当前采购单
                        $purchaseItem->purchaseItem->send_quantity = $purchaseItem->quantity;
                        $purchaseItem->purchaseItem->save();
                        $purchaseItem->status = 1;
                        $purchaseItem->save();
                    } else {  // 非当前采购单
                        if ($purchaseItem->status != 1) {
                            throw new AccidentException('采购单未入库', Code::OPERATE_FAIL);
                        }
                        // 消耗库存
                        $stockService = new StockService();
                        $stockService->setOperateSn($order->order_id)
                            ->unlockAndConsume($purchaseItem->lock_id, StockLockLog::SOURCE_ORDER_DELIVERED, StockChangeLogs::SOURCE_ORDER_DEDUCTION);
                    }
                });
                $item->stockItems->each(function ($stockItem) use ($order) {  // 消耗库存
                    $stockService = new StockService();
                    $stockService->setOperateSn($order->order_id)
                        ->unlockAndConsume($stockItem->lock_id, StockLockLog::SOURCE_ORDER_DELIVERED, StockChangeLogs::SOURCE_ORDER_DEDUCTION);
                });
            });

            $order->order_status = OrderModel::STATUS_SHIPPED;
            $order->save();
            return true;
        });
    }

    public function updateStatus()
    {
        validator($this->formData, [
            'ids' => 'required|array'
        ])->validate();

        $status = PurchaseOrdersModel::STATUS_PENDING;//待下单
        $statusName = $this->model::getStatusName($status);

        $list = $this->model::query()->whereIn('id', $this->formData['ids'])->get();
        $list->each(function ($item) use ($status, $statusName) {
            $logContent = "采购状态变更：{$item->status_name} => {$statusName}";

            //添加日志
            $logData = [
                'purchase_id' => $item->id,
                'operator_type' => PurchaseOrderLogs::OPERATOR_TYPE_STATUS_CHANGE,
                'content' => $logContent,
            ];
            PurchaseOrderLogs::addLog($logData);

            $item->update(['status' => $status]);
        });

        return true;
    }

    public function getPurchaseOrderLogs()
    {
        $query = PurchaseOrderLogs::query()->with(['admin'])->where('purchase_id', $this->formData['purchase_id']);

        return $query->orderBy('id', 'desc')->paginate($this->formData['size']);
    }

    public function inStorageRules()
    {
        return [
            'items' => 'required|array',
            'items.*.id' => 'required|int',
            'items.*.quantity' => 'required|int',
            'items.*.location_code' => 'required|string',
        ];
    }

    protected function rules()
    {
        return [
            'shop_order_id'     => 'sometimes|int',
            'shop_id'           => 'sometimes|int',
            'goods'             => 'required|array',
            'goods.*.sku_id'    => 'required|int',
            'goods.*.quantity'  => 'required|int|min:1',
            'goods.*.order_items' => 'sometimes|array',
            'goods.*.order_items.*.order_sn' => 'required',
            'goods.*.order_items.*.order_id' => 'required',
            'goods.*.order_items.*.order_item_id' => 'required|int'
        ];
    }

    protected function orderDeliverRules()
    {
        return [
            'order_id' => 'required|int',
            'purchase_id' => 'required|int',
        ];
    }

}
