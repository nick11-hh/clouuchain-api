<?php

namespace App\Services\Admin;

use App\Jobs\QueryTrackingJob;
use App\Lib\Code;
use App\Models\ExpressOrderAddressModel;
use App\Models\ExpressOrderItemsModel;
use App\Models\ExpressOrderModel;
use App\Models\ExpressOrderTrackingModel;
use App\Models\HandMovementModel;
use App\Models\LogisticsApplyModel;
use App\Models\Order;
use App\Models\OrderDeclarationModel;
use App\Models\ShopOrderExpressOrderMappingsModel;
use App\Models\ShopOrderLogs;
use App\Models\WarehouseAddress;
use App\Services\Tracking\TrackingService;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class ExpressOrderService extends BaseService
{
    public function __construct(ExpressOrderModel $model)
    {
        $this->model    = $model;
        $this->query    = $model->newQuery();
        $this->formData = request()->all();
    }

    public function index()
    {
        return parent::index();
    }

    public function store(): bool
    {

        return true;
    }

    public function create($order, $params = [])
    {
        // $order = Order::with([
        //     'lineItems.mapping.goodsSku',
        //     'shippingAddress',
        //     'channel:id,code,name,spec,express_companies_id',
        //     'shop',
        //     'expressOrders:id,package_sn'
        //                      ])->where('id', $order->id)->first();

        if (empty($order)) {
            return '';
        }

        return DB::transaction(function () use ($order, $params) {
            $warehouse = WarehouseAddress::query()->first();

            //物流订单信息
            $data = [
                'order_sn'               => $order->order_id,
                'express_companies_id'   => $order->channel->express_companies_id ?? 0, //物流公司ID
                'express_companies_code' => $order->logistics_provider_code ?? '', //物流公司编码
                'logistics_channel_id'   => $order->channel->id ?? 0, //物流渠道ID
                'express_line_id'        => $order->express_line_id ?? 0, //渠道路线ID
                'warehouse_id'           => $warehouse->id ?? 0, //仓库ID
                'change_type'            => $params['change_type'] ?? 1, //更换物流原因：1-首次申请 2-修改运单信息 3-订单拆分 4-订单合并 5-更换物流渠道 6-其他
                'change_remark'          => $params['change_remark'] ?? '', //更换物流备注
            ];

            $data         = ExpressOrderModel::init($data);
            $expressOrder = ExpressOrderModel::query()->create($data);

            //物流订单地址信息
            $address = [
                'express_order_id'       => $expressOrder->id ?? 0, //物流订单ID
                'consignee_name'         => $order->shippingAddress->name ?? '', //收件人全称
                'consignee_company'      => $order->shippingAddress->company ?? '', //收件人公司
                'consignee_first_name'   => $order->shippingAddress->first_name ?? '', //收件人名
                'consignee_last_name'    => $order->shippingAddress->last_name ?? '', //收件人姓
                'consignee_address1'     => $order->shippingAddress->address1 ?? '', //收件人地址1
                'consignee_address2'     => $order->shippingAddress->address2 ?? '', //收件人地址2
                'consignee_phone'        => $order->shippingAddress->phone ?? '', //收件人电话
                'consignee_email'        => $order->shippingAddress->email ?? '', //收件人邮箱
                'consignee_city'         => $order->shippingAddress->city ?? '', //收件人城市
                'consignee_zip'          => $order->shippingAddress->zip ?? '', //收件人邮编
                'consignee_province'     => $order->shippingAddress->province ?? '', //收件人省/州
                'consignee_country'      => $order->shippingAddress->country ?? '', //收件人国家
                'consignee_country_code' => $order->shippingAddress->country_code ?? '', //收件人国家代码
                'consignee_tax'          => $order->shippingAddress->tax ?? '', //收件人税号
            ];

            $address = ExpressOrderAddressModel::init($address);
            ExpressOrderAddressModel::query()->create($address);

            //物流订单详情信息
            // 判断是否为手动报关
            $logistics = null;
            if ($order->is_hand_customs) {
                $logistics = HandMovementModel::query()->where('order_id', $order->id)->first();
            }

            $weight = 0;
            $order->lineItems->each(function ($item) use ($expressOrder, $logistics, &$weight) {
                if (empty($logistics)) {
                    $logistics = OrderDeclarationModel::query()->where('order_item_id', $item->id)->first();
                }

                if (!$logistics) {
                    return false;
                }

                $declareWeight = $logistics->weight;
                $itemData      = [
                    'express_order_id'   => $expressOrder->id ?? 0, //物流订单ID
                    'cn_name'            => $logistics->cn_name ?? '', //报关中文名
                    'en_name'            => $logistics->en_name ?? '', //报关英文名
                    'unit_price'         => $logistics->unit_price ?? 0, //报关单价(USD)
                    'weight'             => $declareWeight, //报关重量(g)
                    'hs_code'            => $logistics->code ?? '', //海关编码
                    'attributes'         => $logistics->attributes ?? [], //物品属性
                    'quantity'           => $item->quantity ?? 1, //数量
                    'material'           => $logistics->material ?? '', //材质
                    'use_to'             => $logistics->use_to ?? '', //用途
                    'sku'                => $item->mapping->goodsSku->sku_id ?? '', //sku
                    'shop_order_id'      => $item->order_id, //订单主键
                    'shop_order_item_id' => $item->id, //订单商品主键
                ];

                $itemData = ExpressOrderItemsModel::init($itemData);
                ExpressOrderItemsModel::query()->create($itemData);

                $weight += $declareWeight * $item->quantity;

                return true;
            });

            //保存包裹重量(g)
            $expressOrder->package_weight = $weight;
            $expressOrder->save();

            //保存店铺订单跟物流订单的映射关系 暂时只考虑一对一，后续再补充多对多
            $mapping = [
                'express_order_id' => $expressOrder->id ?? 0,
                'shop_order_id'    => $order->id ?? 0,
            ];
            ShopOrderExpressOrderMappingsModel::query()->create($mapping);

            $packageSn = $expressOrder->package_sn ?? '';

            //记录日志
            $logData = [
                'order_id'      => $order->id,
                'operator_type' => ShopOrderLogs::OPERATOR_TYPE_CHANGE_LOGISTICS,
                'content'       => '创建包裹：' . $packageSn,
            ];
            ShopOrderLogs::addLog($logData);

            //返回物流包裹号
            return $expressOrder;
        });
    }

    /**
     * 查询物流轨迹
     */
    public function queryTracking($params, $isReturnTrackList = false)
    {
        validator($params, [
            'packages' => 'required|array'
        ])->validate();

        //开启物流轨迹
        $enableTracking = TrackingService::enableTracking();
        throw_unless(
            $enableTracking,
            new AccidentException('请先配置并启用物流查询授权', Code::OPERATE_FAIL)
        );

        //查询申请成功的订单
        $expressOrders = ExpressOrderModel::query()
                                          ->with(['logistics', 'tracking'])
                                          ->whereIn('package_sn', $params['packages'])
                                          ->where('status', ExpressOrderModel::STATUS_SUCCESS)
                                          ->get();
        if ($expressOrders->isEmpty()) {
            return true;
        }

        $trackList = [];
        $expressOrders->each(function ($item) use ($isReturnTrackList, &$trackList) {
            try {

                //跳过历史数据
                if (empty($item->logistics)) {
                    return false;
                }

                //跟踪号注册物流轨迹
                if (empty($item->tracking)) {
                    $item->tracking = $this->registerTracking($item->package_sn);
                }

                //物流轨迹状态
                $trackData   = $this->updateTracking($item->tracking, $isReturnTrackList);
                $trackList[] = $trackData;
            } catch (\Throwable $e) {

                info('查询物流轨迹失败', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            }


            return true;
        });

        return $isReturnTrackList ? $trackList : true;
    }

    /**
     * 跟踪号注册物流轨迹
     */
    public function registerTracking($packageSn)
    {
        if (empty($packageSn)) {
            return false;
        }

        //物流轨迹
        $enableTracking = TrackingService::enableTracking();
        if (empty($enableTracking)) {
            return false;
        }

        //查询申请成功的订单
        $expressOrder = ExpressOrderModel::query()
                                         ->with(['logistics'])
                                         ->where('package_sn', $packageSn)
                                         ->where('status', ExpressOrderModel::STATUS_SUCCESS)
                                         ->first();
        if (empty($expressOrder)) {
            return false;
        }

        //物流跟踪号
        $trackingNumber = $expressOrder->logistics->way_bill_number ?? '';
        if (empty($trackingNumber)) {
            return false;
        }

        //运输商代码
        $carrierCode = '';

        $remark = '';

        //注册物流轨迹
        try {
            (new TrackingService())->register($trackingNumber, $carrierCode);
        } catch (\Exception $e) {
            logger('物流轨迹注册失败，错误原因为：' . $e->getMessage());

            $remark = $e->getMessage();
        }

        //保存物流轨迹信息
        $data = [
            'express_order_id' => $expressOrder->id,
            'tracking_number'  => $trackingNumber,
            'carrier_code'     => $carrierCode,
            'remark'           => $remark,
        ];

        $data = ExpressOrderTrackingModel::init($data);
        return ExpressOrderTrackingModel::query()->create($data);
    }

    /**
     * 更新物流轨迹状态
     */
    public function updateTracking($tracking, $isReturnTrackList = false)
    {
        if (empty($tracking)) {
            return false;
        }

        //物流轨迹
        $enableTracking = TrackingService::enableTracking();
        if (empty($enableTracking)) {
            return false;
        }

        //注册物流轨迹
        try {
            $trackInfo = (new TrackingService())->query($tracking->tracking_number, $tracking->carrier_code, $isReturnTrackList);
            logger('物流轨迹详情', ['tracking_number' => $tracking->tracking_number, 'track_info' => $trackInfo]);
        } catch (\Exception $e) {
            logger('物流轨迹查询失败，错误原因为：' . $e->getMessage());
        }

        //保存物流轨迹信息
        if (isset($trackInfo['error'])) {
            $tracking->remark = json_encode($trackInfo['error']);
        }

        if ($isReturnTrackList) {
            $trackingStatus    = $trackInfo['latest_status']['status'] ?? '';
            $trackingSubStatus = $trackInfo['latest_status']['sub_status'] ?? '';
        } else {
            $trackingStatus    = $trackInfo['track_info']['latest_status']['status'] ?? '';
            $trackingSubStatus = $trackInfo['track_info']['latest_status']['sub_status'] ?? '';
        }

        $tracking->carrier_code        = $trackInfo['carrier'] ?? '';//物流商代码
        $tracking->status              = ExpressOrderTrackingModel::track17StatusMap()[$trackingStatus] ?? 0;//物流轨迹状态
        $tracking->tracking_status     = $trackingStatus;//物流主状态
        $tracking->tracking_sub_status = $trackingSubStatus;//物流子状态

        $tracking->save();

        $tracking->track_info = $trackInfo ?? [];
        return $tracking;
    }

    public function createOrUpdateByDianxiaomiImport($order, $importRowData, $importType)
    {
        if (empty($order)) return null;

        return DB::transaction(function () use ($order, $importRowData, $importType) {
            //判断包裹是否已经创建过
            if ($order->expressOrders->isNotEmpty()) {
                $this->updateExpressOrder($order, $importRowData);
            } else {
                $this->createExpressOrder($order, $importRowData);
            }

            $title = '导入订单-虚假发货(店小秘)';
            if ($importType == 'order_info') $title = '导入订单-更新订单信息(店小秘)';

            $sku        = implode(',', $importRowData['sku']);
            $logContent = "{$title}；sku：{$sku}，物流单号: {$importRowData['tracking_number']}; 物流渠道：{$importRowData['logistics_channel']}";

            ShopOrderLogs::addLog([
                'order_id'      => $order->id,
                'operator_type' => $importType == 'order_info' ? ShopOrderLogs::OPERATOR_TYPE_SYNC_THIRD_PARTY_TRACK_NUMBER : ShopOrderLogs::OPERATOR_TYPE_SYNC_THIRD_PARTY_SEND,
                'content'       => $logContent,
            ]);

            return true;
        });
    }

    public function createExpressOrder($order, $params)
    {
        $warehouse = WarehouseAddress::query()->first();

        //物流订单信息
        $data = [
            'order_sn'               => $order->order_id,
            'express_companies_id'   => $order->channel->express_companies_id ?? 0, //物流公司ID
            'express_companies_code' => $order->logistics_provider_code ?? '', //物流公司编码
            'logistics_channel_id'   => $order->channel->id ?? 0, //物流渠道ID
            'express_line_id'        => $order->express_line_id ?? 0, //渠道路线ID
            'warehouse_id'           => $warehouse->id ?? 0, //仓库ID
            'change_type'            => $params['change_type'] ?? 1, //更换物流原因：1-首次申请 2-修改运单信息 3-订单拆分 4-订单合并 5-更换物流渠道 6-其他
            'change_remark'          => $params['change_remark'] ?? '', //更换物流备注
            'status'                 => $params['tracking_number'] ? $this->model::STATUS_SUCCESS : $this->model::STATUS_APPLY, //申请状态
        ];

        $expressOrder = ExpressOrderModel::query()->create(ExpressOrderModel::init($data));

        //物流订单地址信息
        $address = [
            'express_order_id'       => $expressOrder->id ?? 0, //物流订单ID
            'consignee_name'         => $order->shippingAddress->name ?? '', //收件人全称
            'consignee_company'      => $order->shippingAddress->company ?? '', //收件人公司
            'consignee_first_name'   => $order->shippingAddress->first_name ?? '', //收件人名
            'consignee_last_name'    => $order->shippingAddress->last_name ?? '', //收件人姓
            'consignee_address1'     => $order->shippingAddress->address1 ?? '', //收件人地址1
            'consignee_address2'     => $order->shippingAddress->address2 ?? '', //收件人地址2
            'consignee_phone'        => $order->shippingAddress->phone ?? '', //收件人电话
            'consignee_email'        => $order->shippingAddress->email ?? '', //收件人邮箱
            'consignee_city'         => $order->shippingAddress->city ?? '', //收件人城市
            'consignee_zip'          => $order->shippingAddress->zip ?? '', //收件人邮编
            'consignee_province'     => $order->shippingAddress->province ?? '', //收件人省/州
            'consignee_country'      => $order->shippingAddress->country ?? '', //收件人国家
            'consignee_country_code' => $order->shippingAddress->country_code ?? '', //收件人国家代码
            'consignee_tax'          => $order->shippingAddress->tax ?? '', //收件人税号
        ];

        $address = ExpressOrderAddressModel::init($address);
        ExpressOrderAddressModel::query()->create($address);

        //物流订单详情信息
        // 判断是否为手动报关
        $logistics = null;
        if ($order->is_hand_customs) {
            $logistics = HandMovementModel::query()->where('order_id', $order->id)->first();
        }

        $weight = 0;
        $order->lineItems->each(function ($item) use ($expressOrder, $logistics, &$weight, $params) {
            //根据variant_id判断包裹item,不在当前导入的订单则跳过 订单商品不在导入的sku时怎说明进行了拆单，会保存到起到包裹
            if (!in_array($item->variant_id, $params['sku'])) return true;

            if (empty($logistics)) $logistics = OrderDeclarationModel::query()->where('order_item_id', $item->id)->first();

            $declareWeight = $logistics->weight ?? 0;
            $itemData      = [
                'express_order_id'   => $expressOrder->id ?? 0, //物流订单ID
                'cn_name'            => $logistics->cn_name ?? '', //报关中文名
                'en_name'            => $logistics->en_name ?? '', //报关英文名
                'unit_price'         => $logistics->unit_price ?? 0, //报关单价(USD)
                'weight'             => $declareWeight, //报关重量(g)
                'hs_code'            => $logistics->code ?? '', //海关编码
                'attributes'         => $logistics->attributes ?? [], //物品属性
                'quantity'           => $item->quantity ?? 1, //数量
                'material'           => $logistics->material ?? '', //材质
                'use_to'             => $logistics->use_to ?? '', //用途
                'sku'                => $item->mapping->goodsSku->sku_id ?? '', //sku
                'shop_order_id'      => $item->order_id, //订单主键
                'shop_order_item_id' => $item->id, //订单商品主键
            ];

            $itemData = ExpressOrderItemsModel::init($itemData);
            ExpressOrderItemsModel::query()->create($itemData);

            $weight += $declareWeight * $item->quantity;

            return true;
        });

        //保存包裹重量(g)
        $expressOrder->package_weight = $weight;
        $expressOrder->save();

        //保存店铺订单跟物流订单的映射关系 暂时只考虑一对一，后续再补充多对多
        $mapping = [
            'express_order_id' => $expressOrder->id ?? 0,
            'shop_order_id'    => $order->id ?? 0,
        ];
        ShopOrderExpressOrderMappingsModel::query()->create($mapping);

        //保存物流申请信息
        $logisticsApplyData = [
            'order_id'                 => $order->order_id,
            'remark'                   => '',
            'way_bill_number'          => $params['tracking_number'] ?? '',
            'fulfillment_express_line' => $params['logistics_channel'] ?? '',
            'tracking_number'          => $params['tracking_number'] ?? '',
            'express_order_id'         => $expressOrder->id,
            'package_sn'               => $expressOrder->package_sn,
        ];
        LogisticsApplyModel::query()->create($logisticsApplyData);

        return $expressOrder;
    }

    public function updateExpressOrder($order, $params)
    {
        if (empty($order)) return false;

        //根据导入的订单及sku查询到对应的包裹，判断是否需要更新跟踪号
        $order->lineItems->each(function ($item) use ($order, $params) {
            //根据variant_id判断包裹item,不在当前导入的订单或者sku不正确时则跳过 订单商品不在导入的sku时可能进行了拆单，会单独创建包裹
            if (!in_array($item->variant_id, $params['sku'])) return true;

            //根据商品主键查询包裹
            $expressOrder = ExpressOrderModel::query()->whereHas('items', function ($query) use ($item) {
                $query->where('shop_order_id', $item->order_id)->where('shop_order_item_id', $item->id);
            })->first();

            //sku没有包裹则创建
            if (empty($expressOrder)) return $this->createExpressOrder($order, $params);

            //更新物流跟踪号
            $wayBillNumber = $expressOrder->logistics->way_bill_number ?? '';
            if ($wayBillNumber !== $params['tracking_number']) {
                $expressOrder->logistics->way_bill_number          = $params['tracking_number'];
                $expressOrder->logistics->fulfillment_express_line = $params['logistics_channel'] ?? '';
                $expressOrder->logistics->tracking_number          = $params['tracking_number'] ?? '';
                $expressOrder->logistics->save();

                //更换物流
                if ($order->is_shipping === Order::IS_SHIPPING_YES) {
                    $order->update([
                        'is_change'     => 1,
                        'change_status' => Order::STATUS_APPLY_NUM_SUCCESS_CHANGE,
                    ]);
                }
            }

            return true;
        });

        return true;
    }


}
