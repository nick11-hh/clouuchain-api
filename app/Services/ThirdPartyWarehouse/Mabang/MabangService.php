<?php

namespace App\Services\ThirdPartyWarehouse\Mabang;

use App\Helper\CurrencyConverter;
use App\Jobs\FulfillmentOrderJob;
use App\Jobs\PushGoodsToMabangJob;
use App\Lib\Code;
use App\Models\Custom;
use App\Models\CustomGroup;
use App\Models\ExpressOrderModel;
use App\Models\Goods;
use App\Models\GoodsSku;
use App\Models\LogisticsApplyModel;
use App\Models\Order;
use App\Models\OrderThirdPartyFulfillmentLogs;
use App\Models\Package;
use App\Models\ShopOrderLogs;
use App\Models\SystemConfig;
use App\Models\ThirdPartyWarehouseConfig;
use App\Models\ShopModel;
use App\Services\Admin\ExpressOrderService;
use App\Services\Base\OrderBaseService;
use App\Services\Base\PackageBaseService;
use App\Services\Base\SystemConfigService as SystemConfigBaseService;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use App\Services\ThirdPartyWarehouse\ThirdPartyWarehouseAbstract;
use App\Services\ThirdPartyWarehouse\ThirdPartyWarehouseInterface;
use App\Services\Tracking\TrackingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AccidentException;

class MabangService extends ThirdPartyWarehouseAbstract implements ThirdPartyWarehouseInterface

{

    protected RequestApi $request;

    protected ThirdPartyWarehouseConfig $config;

    public function __construct($config)
    {
        $this->request = new RequestApi($config);
        $this->config = $config;
    }

    /**
     * @param $params
     * @return void
     */
    public function syncWarehouseProduct($params): void
    {
        $sync = true;
        $count = 0;
        $maxSyncCount = 15;
        do {
            $count ++;
            try {
                $params = [
                    'maxRows' => 1000,
                    'cursor' => $count
                ];
                $result = $this->request->getStockSku($params);
            } catch (Exception $e) {}
        } while ($sync && $count < $maxSyncCount);
    }

    /**
     * @param $product
     * @param $params
     * @return void
     */
    public function updateWarehouseProduct($product, $params)
    {
        $result = $this->request->updateStockSku('123123121', ['virtualSkus' => '5374864121452334']);
        dd($result);
    }

    /**
     * @param $order
     * @return true
     * @throws Exception
     */
    public function pushOrderToWarehouse($order): bool
    {
        $params = $this->formatOrder($order);
        $result = $this->request->createOrder($params);

        if ($result['code'] != 200) {
            throw new AccidentException('推送订单到马帮失败：' . $result['message'] );
        }
        return true;
    }

    public function checkOrderCreate($order, $pushLog = null, $exception = false)
    {
        return DB::transaction(function () use ($order, $pushLog, $exception) {
            $result = $this->request->checkOrderCreate($order->order_id);
            if (!empty($result['data']['isok'])) {
                $order->fulfillment_push_status = Order::FULFILLMENT_PUSH_SUCCESS;
                $order->save();
                ShopOrderLogs::addLog(['order_id' => $order->id, 'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PUSH_THIRD_PARTY_SUCCESS, 'content' => '推送成功，马帮系统已接收订单', 'operator_id' => $pushLog->operate_id]);
                if (!empty($pushLog)) {
                    $pushLog->status = OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_SUCCESS;
                    $pushLog->content = '对方系统已接收订单';
                    $pushLog->save();
                }

                if ($this->config->mark_in_distribution_after_push_order == ThirdPartyWarehouseConfig::MARK_IN_DISTRIBUTION_AFTER_PUSH_ORDER_ENABLE) {
                    //标记配货中
                    $this->markInDistribution($order, $pushLog);
                }

                return true;
            }
            if ($exception) {
                throw new AccidentException('马帮ERP接收失败');
            }
            ShopOrderLogs::addLog(['order_id' => $order->id, 'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PUSH_THIRD_PARTY_ERROR, 'content' => '推送失败，马帮系统未接收订单', 'operator_id' => $pushLog->operate_id]);
            $order->fulfillment_push_status = Order::FULFILLMENT_PUSH_ERROR;
            $order->save();
            if (!empty($pushLog)) {
                $pushLog->status = OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_ERROR;
                $pushLog->content = '对方系统接收订单失败';
                $pushLog->save();
            }
            return false;
        });
    }

    /**
     * 标记订单为配货中
     * @param $order
     * @param $pushLog
     * @return void
     * @throws Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/14 17:24
     */
    public function markInDistribution($order, $pushLog)
    {
        $markDistributionResult = $this->request->markInDistribution($order->order_id);
        if ($markDistributionResult['code'] == 200 && count($markDistributionResult['data']['platformOrderIdsForSuccess'] ?? []) > 0) {
            ShopOrderLogs::addLog(['order_id' => $order->id, 'operator_type' => ShopOrderLogs::OPERATOR_TYPE_MARK_IN_DISTRIBUTION, 'content' => '马帮系统标记配货中成功', 'operator_id' => $pushLog->operate_id]);
        } else {
            ShopOrderLogs::addLog(['order_id' => $order->id, 'operator_type' => ShopOrderLogs::OPERATOR_TYPE_MARK_IN_DISTRIBUTION, 'content' => '马帮系统标记配货失败,请检查订单状态', 'operator_id' => $pushLog->operate_id]);
        }
    }

    /**
     * @param $order
     * @return mixed
     */
    public function getOrderDetail($order): mixed
    {
        $result = $this->request->getOrderDetail($order->order_id);
        if ($result['code'] != 200) {
            return [];
        }
        if ($result['data']['total'] === 0) return [];
        return $result['data']['data'][0] ?? [];
    }

    /**
     * 批量获取订单详情（最多10个）
     * @param array $orderIds 订单ID数组
     * @return array 以 order_id 为键的订单详情数组
     */
    public function batchGetOrderDetails(array $orderIds): array
    {
        if (empty($orderIds)) return [];

        $result = $this->request->batchGetOrderDetail($orderIds);
        if ($result['code'] != 200 || empty($result['data']['data'])) {
            return [];
        }

        // 以 platformOrderId 为键构建映射
        $detailsMap = [];
        foreach ($result['data']['data'] as $detail) {
            $detailsMap[$detail['platformOrderId']] = $detail;
        }
        return $detailsMap;
    }

    /**
     * 批量同步订单发货状态
     * @param \Illuminate\Support\Collection $orders 订单集合
     * @return array 处理结果
     */
    public function batchSyncOrderSendStatus($orders): array
    {
        $orderIds = $orders->pluck('order_id')->toArray();
        $orderDetailsMap = $this->batchGetOrderDetails($orderIds);

        $results = [];
        foreach ($orders as $order) {
            $orderDetail = $orderDetailsMap[$order->order_id] ?? null;
            $results[$order->order_id] = $this->syncOrderSendStatusWithDetail($order, $orderDetail);
        }
        return $results;
    }

    /**
     * 使用已有的订单详情同步发货状态
     * @param $order
     * @param array|null $orderDetail
     * @return array
     */
    public function syncOrderSendStatusWithDetail($order, ?array $orderDetail): array
    {
        if (empty($orderDetail)) return ['is_send' => false, 'data' => []];
        return $this->processOrderSendStatus($order, $orderDetail);
    }

    /**
     * @param $order
     * @return array
     */
    public function syncOrderSendStatus($order): array
    {
        $orderDetail = $this->getOrderDetail($order);
        if (empty($orderDetail)) return ['is_send' => false, 'data' => []];
        return $this->processOrderSendStatus($order, $orderDetail);
    }

    /**
     * 处理订单发货状态同步逻辑（内部复用方法）
     * @param $order
     * @param array $orderDetail
     * @return array
     */
    protected function processOrderSendStatus($order, array $orderDetail): array
    {
        try {
            if (!empty($orderDetail['trackNumber'])) {
                // @todo 后续需要实现拆单合单导入
                // 马帮发货暂时不考虑拆包合包，默认一个包裹

                // 检查订单是否有包裹
                if ($order->packages->isEmpty()) {
                    info('批量同步马帮状态-订单无包裹', [
                        'order_id' => $order->order_id ?? 'N/A',
                        'order_db_id' => $order->id ?? 'N/A',
                        'track_number' => $orderDetail['trackNumber'] ?? null,
                        'order_status' => $order->order_status ?? null,
                        'fulfillment_platform' => $order->fulfillment_platform ?? null,
                        'paymented_at' => $order->paymented_at ?? null,
                        'error' => '订单没有包裹，跳过运单号同步',
                        'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
                    ]);
                    return ['data' => $orderDetail, 'is_send' => false];
                }

                // 使用 first() 方法安全获取第一个包裹
                $package = $order->packages->first();
                if (empty($package)) {
                    info('批量同步马帮状态-包裹为空', [
                        'order_id' => $order->order_id ?? 'N/A',
                        'order_db_id' => $order->id ?? 'N/A',
                        'track_number' => $orderDetail['trackNumber'] ?? null,
                        'packages_count' => $order->packages->count(),
                        'error' => '订单包裹为空，跳过运单号同步',
                        'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
                    ]);
                    return ['data' => $orderDetail, 'is_send' => false];
                }

                $logisticsApply = LogisticsApplyModel::query()->where('package_id', $package->id)->latest()->first();
                $orderDetail['last_mail_tracking_number'] = $orderDetail['trackNumber'] != $orderDetail['trackNumber1'] ? $orderDetail['trackNumber'] : '';
                if (empty($orderDetail['trackNumber1'])) $orderDetail['trackNumber1'] = $orderDetail['trackNumber'];


                // @todo 处理老数据，后面删除
                if (!empty($logisticsApply) && $logisticsApply->way_bill_number != $orderDetail['trackNumber1'] && $logisticsApply->created_at < '2025-08-05' ) {
                    $logisticsApply->way_bill_number = $orderDetail['trackNumber1'];
                    $logisticsApply->last_mail_tracking_number = $orderDetail['last_mail_tracking_number'];
                    $logisticsApply->save();
                }

                if (empty($logisticsApply) || $logisticsApply->way_bill_number != $orderDetail['trackNumber1']
                    || (!empty($orderDetail['last_mail_tracking_number']) && $logisticsApply->last_mail_tracking_number != $orderDetail['last_mail_tracking_number'])) {

                    // 创建新的单号记录
                    if (empty($logisticsApply) || $logisticsApply->way_bill_number != $orderDetail['trackNumber1']) {
                        $logisticsApply = new LogisticsApplyModel();
                        $logisticsApply->package_id = $package->id;
                        $logisticsApply->remark = '';
                    }
                    // 更新单号
                    $logisticsApply->way_bill_number = $orderDetail['trackNumber1'];
                    // 更新尾程单号
                    if (!empty($orderDetail['last_mail_tracking_number'])) {
                        if ($logisticsApply->last_mail_tracking_number != $orderDetail['last_mail_tracking_number']) { // 尾程单号获取时间
                            $logisticsApply->last_mail_time = now();
                        }
                        $logisticsApply->last_mail_tracking_number = $orderDetail['last_mail_tracking_number'];
                    }
                    $logisticsApply->save();

                    ShopOrderLogs::addLog([
                        'order_id'      => $order->id,
                        'operator_type' => ShopOrderLogs::OPERATOR_TYPE_SYNC_THIRD_PARTY_SEND,
                        'content'       => '运单号申请成功，马帮系统已生成运单号，自动同步运单号: ' . $logisticsApply->way_bill_number,
                        'operator_id'   => 0
                    ]);

                    // 通知平台订单发货
                    $sync_waybill_number = SystemConfigBaseService::getConfigValue(SystemConfig::SYNC_WAYBILL_NUMBER);
                    if($sync_waybill_number == 1) {
                        (new PackageBaseService($package))->packagePlatformDelivery();
                    }

                    //注册17track物流轨迹
                    try {
                        (new TrackingService())->registerByLogisticsApply($logisticsApply);
                    } catch (\Exception $e) {
                        info('注册17track物流轨迹失败', [
                            'order_id' => $order->order_id ?? 'N/A',
                            'package_id' => $package->id ?? null,
                            'logistics_apply_id' => $logisticsApply->id ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }

                }

                // 更新运单状态
                $order->logistics_status = Package::LOGISTICS_APPLY_SUCCESS;
                $order->save();
                $package->logistics_status = Package::LOGISTICS_APPLY_SUCCESS;
                $package->save();
            }
        } catch (\Exception $e) {
            // 捕获处理运单号时的异常，记录详细错误信息
            info('批量同步马帮状态-处理运单号异常', [
                'order_id' => $order->order_id ?? 'N/A',
                'order_db_id' => $order->id ?? 'N/A',
                'track_number' => $orderDetail['trackNumber'] ?? null,
                'packages_count' => $order->packages->count() ?? 0,
                'package_id' => isset($package) ? $package->id : null,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'order_detail' => $orderDetail ?? null,
            ]);
            // 抛出异常，让上层处理
            throw $e;
        }

        //已发货，已完成状态，通知订单平台履约
        if ($order->order_status === Order::STATUS_PENDING && in_array($orderDetail['orderStatus'], [3, 4])) {
            // 同步更新到马帮的其他收入和其他支出金额
            try {
                if ($order->fulfillment_platform == ThirdPartyWarehouseConfig::PLATFORM_MABANG) {
                    $this->updateOrderData($order, OrderThirdPartyFulfillmentLogs::UPDATE_ORDER_REFUND_AND_SUPPLEMENT_AMOUNT);
                }
            } catch (\Exception $exception) {
                info('同步其他收入和其他支出金额到马帮失败', [
                    'order_id' => $order->order_id ?? 'N/A',
                    'order_db_id' => $order->id ?? 'N/A',
                    'error_message' => $exception->getMessage(),
                    'error_file' => $exception->getFile(),
                    'error_line' => $exception->getLine(),
                ]);
            }
            return ['data' => $orderDetail, 'is_send' => true];
        }
        return ['data' => $orderDetail, 'is_send' => false];
    }

    /**
     * 更新订单
     * @throws Exception
     */
    public function updateOrderData($order, $type = 1, $extraUpdateData = [])
    {
        $params = [
            'platformOrderId' => $order->order_id,
        ];

        //1 更新地址 2 更新其他收入，其他支出金额
        if ($type == OrderThirdPartyFulfillmentLogs::UPDATE_ORDER_SHIPPING_ADDRESS) {
            $updateData =[
                'buyerName'     => $extraUpdateData['first_name'] . ' ' . $extraUpdateData['last_name'],
                'phone1'        => $extraUpdateData['phone'],
                'country'       => $extraUpdateData['country'],
                'shortAddress'  => $extraUpdateData['company'] ?? '',
                'countryCode'   => $extraUpdateData['country_code'],
                'province'      => $extraUpdateData['province'],
                'city'          => $extraUpdateData['city'],
                'street1'       => $extraUpdateData['address1'],
                'street2'       => $extraUpdateData['address2'],
                'abnnumber'     => $extraUpdateData['tax'] ?? '',
                'email'         => $extraUpdateData['email'] ?? '',
                'postCode'      => $extraUpdateData['zip'] ?? '',
            ];
        } else {
            //马帮接口暂时不支持修改其他收入，等待马帮接口支持后生效
            $supplementPrice    = round($order->supplement_price ?: 0, 2);
            $refundPrice        = round($order->refund_price ?: 0, 2);
            $currencyConverter = new CurrencyConverter();
            $supplementPrice = $currencyConverter->convert($supplementPrice);
            $refundPrice = $currencyConverter->convert($refundPrice);
            $updateData = [
                'otherIncome' => $supplementPrice,
                'otherExpend' => $refundPrice,
                'extendAttr'  => json_encode([
                    ['key' => 'otherIncome', 'val' => $supplementPrice]
                ])
            ];
        }

        $params = array_merge($updateData, $params);

        $result = $this->request->updateOrderData($params);
        if ($result['code'] != 200) {
            throw new AccidentException('修改马帮订单失败，'. $result['message']);
        }

        return true;
    }

    public function addMabangStock(array $goodsInfo)
    {
        return $this->request->addMabangStock($goodsInfo);
    }

    public function updateMabangStock(array $params)
    {
        return $this->request->updateMabangStock($params);
    }

    public function skuLinkBindMabangStock(array $params)
    {
        return $this->request->skuLinkBindMabangStock($params);
    }

    public function addMabangComboSku($goodsSku)
    {
        $params = $this->formatGoods($goodsSku);
        $result = $this->request->addMabangComboSku($params);
        if ($result['code'] != 200) {
            throw new AccidentException('新增马帮组合SKU失败，'. $result['message']);
        }
        return ['mabang_stock_id' => $result['data']['stockId'] ?? 1];
    }

    public function updateMabangComboSku($goodsSku)
    {
        $params = $this->formatGoods($goodsSku);
        $result = $this->request->updateMabangComboSku($params);
        if ($result['code'] != 200) {
            throw new AccidentException('新增马帮组合SKU失败，'. $result['message']);
        }
        return true;
    }


    /************************************************** protected 方法 ********************************************************/

    /**
     * @param Order $order
     * @return array
     * @throws AccidentException
     */
    protected function formatOrder(Order $order)
    {
        $orderItemList = [];
        foreach ($order->lineItems as $item) {
            // 虚拟品过滤掉不推送给马帮
            $goodsSku = GoodsSku::query()->with('goods')->find($item->goods_sku_id);
            if ($goodsSku && $goodsSku->goods->goods_type === Goods::GOODS_TYPE_VIRTUAL_PRODUCT) continue;
            if ($goodsSku && (int)$goodsSku->mabang_stock_id <= 0) {
                $this->ensureGoodsSkuPushedToMabang($goodsSku);
                $goodsSku->refresh();
                if ((int)$goodsSku->mabang_stock_id <= 0) {
//                    throw new AccidentException('订单商品自动推送马帮失败，无法推送订单。SKU：' . $goodsSku->sku_id);
                }
            }
            $variantTitle = $item->variant_title ?? '';
            if (!empty($item->properties)) {
                foreach ($item->properties as $property) {
                    if (!empty($variantTitle)) $variantTitle .= '/';
                    $variantTitle .= ($property['name'] ?? '') . ':' . ($property['value'] ?? '');
                }
            }
            $orderItemList[] = [
                "title" => $item->title, // 必填；长度上线1000个字符
                "platformSku" => $item->variant_id, // 必填
                "quantity" => $item->quantity, // 必填
                "pictureUrl" => $item->imgs[0] ?? '', // 必填
                "itemId" => $item->product_id,
                "sellPrice" => $item->price,
                "productUnit" => '',  // 产品单位
                "specifics" => $variantTitle,
                "message" => '', // 商品留言
                "productUrl" => $item->product_url,
//                "salesRecordNumber" => '',
//                "isGift" => ''
            ];
        }

        if (empty($orderItemList)) throw new AccidentException('没有需要推送到马帮的订单商品');

        $logistics = [
            "key" => 'logistics_accessories',
            'val' => [
                'a' => $order->logisticsApply->label_url ?? ''
            ]
        ];

        //物流费用 开启商品一口价 物流费用为sku的物流总报价
        $logisticsFee = $order->order_one_price === Order::ONE_PRICE_OPEN ? $order->sku_logistics_fee : $order->logistics_fee;

        //订单报价总计:商品总报价+实际物流报价/sku物流总报价-优惠金额+其他补价（非商品一口价）
        $totalAmount = $order->vendor_price + $logisticsFee - $order->favourable_price + $order->other_supplement_price;

        // mate 推送到马帮使用业务员名称
        if (getCurrentUuid() === '8016a66761e5c96905badaac02edd08b') {
            $customer = Custom::query()->with('customGroup')->where('id', $order->customer_id)->first();
            $shopName = $customer->customGroup->group_name ?? '';
            if (empty($shopName)) {
                throw new AccidentException('店铺名称为空，请先设置客户分组');
            }
        } else {
            $shopName = $order->shop ? $order->shop->shop_name : ShopModel::withTrashed()->where('id', $order->shop_id)->value('shop_name');
        }

        $countryCode = $order->shippingAddress->country_code;
        if ($countryCode === 'XK') $countryCode = 'KS';

        $platformSn = str_replace('#', '', $order->name);
        if (preg_match('/[^a-zA-Z0-9_-]/', $platformSn)) {
            $platformSn = '';
        }

        return [
            'platformOrderId' => $order->order_id,
            'salesRecordNumber' => $platformSn,
            'shopName' => $shopName,
            'myLogisticsChannelId' => $order->myLogisticsChannelId,
            'buyerUserId' => $order->customer_id,
            'buyerName' => $order->shippingAddress->first_name . ' ' . $order->shippingAddress->last_name,
            'phone1' => $order->shippingAddress->phone,
//            'country' => $order->shippingAddress->country,
            'countryCode' => $countryCode,
            'province' => $order->shippingAddress->province,
            'city' => $order->shippingAddress->city,
            'street1' => $order->shippingAddress->address1,
            'street2' => $order->shippingAddress->address2,
            'abnnumber' => $order->shippingAddress->tax,
            'email' => $order->shippingAddress->email,
            'postCode' => $order->shippingAddress->zip,
            'currencyId' => 'USD',
            'itemTotal' => $totalAmount,
            'paidTime' => (string)$order->paymented_at,
            'remark' => $order->remark,
            'buyerMessage' => $order->remark,
            'transNumber' => $order->staff->username ?? '',
            'orderItemList' => $orderItemList,
            'shortAddress' => $order->shippingAddress->company ?? '',
//            'logistics_accessories' => $logistics
        ];
    }

    /**
     * 推送订单前自动补推商品到马帮，确保拿到马帮库存ID
     *
     * @param GoodsSku $goodsSku
     * @return void
     * @throws AccidentException
     */
    protected function ensureGoodsSkuPushedToMabang(GoodsSku $goodsSku): void
    {
        try {
            PushGoodsToMabangJob::dispatchSync(
                PushGoodsToMabangJob::TYPE_11,
                $goodsSku,
                auth('admin')->id() ?: 0
            );
        } catch (\Throwable $e) {
            throw new AccidentException('订单商品推送马帮失败：' . $goodsSku->sku_id . '，' . $e->getMessage());
        }
    }

    public function formatGoods($goodsSku)
    {
        $comboProductDetail = [];
        if ($goodsSku->groupItems->isEmpty()) {
            throw new AccidentException('该组合产品没有设置组合项');
        }
        foreach ($goodsSku->groupItems as $groupItem) {
            $comboProductDetail[] = [
                'stockSku' => $groupItem->goodsSku->sku_id,
                'quantity' => $groupItem->quantity,
            ];
        }

        return [
            'employeeName' => '魏旭平',
            'comboSku' => $goodsSku->sku_id,
            'name' => $goodsSku->goods?->goods_name_cn . ' ' . $goodsSku->spec_name,
            'nameEn' => $goodsSku->goods?->goods_name . ' ' . $goodsSku->spec_name,
            'comboPicture' => $goodsSku->images ? $goodsSku->images[0] : '',
            'length' => $goodsSku->length,
            'width' => $goodsSku->width,
            'height' => $goodsSku->height,
            'weight' => $goodsSku->weight,
            'declareName' => $goodsSku->logistics?->cn_name,
            'declareEname' => $goodsSku->logistics?->en_name,
            'declareFee' => $goodsSku->logistics?->unit_price,
            'declareWeight' => $goodsSku->logistics?->weight,
            'declareCustoms' => $goodsSku->logistics?->code,
            'comboProductDetail' => json_encode($comboProductDetail),
            'status' => 1
        ];
    }

}
