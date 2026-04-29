<?php

namespace App\Services\PlatformShop;

use App\Jobs\SendEmailJob;
use App\Lib\Code;
use App\Mail\MailConfig;
use App\Models\ClientGoods;
use App\Models\ClientGoodsPublishLog;
use App\Models\Order;
use App\Models\ShopModel;
use App\Models\ShopOrderAbnormal;
use App\Models\ShopSetting;
use App\Services\Base\OrderBaseService;
use App\Services\Base\SystemConfigService as SystemConfigBaseService;
use App\Services\PlatformShop\Exceptions\PlatformShopException;
use Exception;
use App\Exceptions\AccidentException;

class PlatformShopService
{

    public ShopModel $shop;

    public PlatformShopInterface $platformService;

    /**
     * @param ShopModel $shop
     * @throws Exception
     */
    public function __construct(ShopModel $shop)
    {
        $this->shop = $shop;
        if (!empty($shop->id)) {
            $this->platformService = Factory::create($shop->platform, $shop);
        } else {
            logger('店铺不存在或者已解绑', [$shop]);
            throw new AccidentException('暂不支持该平台', Code::OPERATE_FAIL);
        }
    }

    /** 设置当前操作的平台
     * @param $platform
     * @return void
     * @throws Exception
     */
    public function setPlatform($platform)
    {
        $this->platformService = Factory::create($platform, $this->shop);
    }

    /** 获取授权链接
     * @param array $params
     * @return mixed
     */
    public function getAuthUrl(array $params = []): mixed
    {
        return $this->platformService->getAuthUrl($params);
    }

    /** 店铺授权接口
     * @param $params
     * @return mixed
     */
    public function authorize($params)
    {
        return $this->platformService->authorize($params);
    }

    /** 同步产品列表
     * @return mixed
     */
    public function syncProductList(): mixed
    {
        return $this->platformService->syncProductList();
    }

    /** 同步单个产品信息
     * @param $productId
     * @return mixed
     */
    public function syncProductDetail($productId): mixed
    {
        return $this->platformService->syncProductDetail($productId);
    }

    /**
     * @param $clientGoods
     * @param array $params
     * @return mixed
     * @throws Exception
     */
    public function productPublish($clientGoods, array $params = []): mixed
    {
        try {
            $platformProduct = $this->platformService->productPublish($clientGoods, $params);
            // 日志
            $info = $this->shop->platform.' publish success, Product ID: ' . $platformProduct['id'];
            $this->addPublishLog($clientGoods, ClientGoodsPublishLog::PUBLISH_SUCCESS, $info);

            // 刊登完成后同步到店铺产品
            $this->syncProductDetail($platformProduct['id']);

            // 更新推送状态
            $clientGoods->status = ClientGoods::STATUS_PUBLISHED;
            $clientGoods->save();

            return $platformProduct;
        } catch (\Exception $e) {
            // 日志
            $clientGoods->status = ClientGoods::STATUS_DEFAULT;
            $clientGoods->save();

            info('刊登失败', [$e->getMessage() . $e->getFile() . $e->getLine()]);
            $info = $this->shop->platform.' publish fail.  reason:' . __($e->getMessage());
            $this->addPublishLog($clientGoods, ClientGoodsPublishLog::PUBLISH_ERROR, $info);
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    /**
     * 同步订单列表
     * @param array $filterParams
     * @return mixed
     */
    public function syncOrderList($filterParams = []): mixed
    {
        return $this->platformService->syncOrderList($filterParams);
    }

    /** 同步单个订单信息
     * @param $orderId
     * @return mixed
     */
    public function syncOrderDetail($orderId): mixed
    {
        return $this->platformService->syncOrderDetail($orderId);
    }

    /** 订单发货
     * @param $shopOrder
     * @param array $params
     * @return mixed
     * @throws Exception
     */
    public function orderShipment($shopOrder, array $params = []): mixed
    {
        //$sync_waybill_number = SystemConfigBaseService::getConfigValue('sync_waybill_number');
        try {
            $result = $this->platformService->orderShipment($shopOrder, $params);
            // 'order_status' => Order::STATUS_DELIVERY_SUCCESS,
            $data = ['send_fail_reason' => ''];

            /*if($sync_waybill_number == 3) {
                $data['order_status'] = Order::STATUS_DELIVERY_SUCCESS;
            }*/

            //修改发货标识
            $data['is_shipping'] = Order::IS_SHIPPING_YES;
            $data['shipment_time'] = now();

            Order::query()->where('id', $shopOrder->id)->update($data);
            logger('履行订单成功: ', $data);

            //发货邮件提醒
            $toEmail = $shopOrder->custom->custom_email;
            if (!empty($toEmail)) {
                $goodsName = '';
                foreach ($shopOrder->allLineItems as $item) {
                    $temp = $item->name . $item->variant_title;

                    $goodsName .= $temp . ' / ';
                }

                $emailParams = [
                    'custom_name'       => $shopOrder->custom->custom_name,
                    'created_at'        => (string)$shopOrder->created_at,
                    'goods_name'        => rtrim($goodsName, ' / '),
                    'order_no'          => $shopOrder->order_id,
                    'express_line'      => $shopOrder->expressLine->name,
                    'way_bill_number'   => $shopOrder->logisticsApply->way_bill_number ?? '',
                ];

                dispatch(new SendEmailJob('ShippingReminderEmail', $toEmail, $emailParams));
            }

            return $result;
        } catch (Exception $e) {
            logger('履行订单失败: ', [$e->getMessage(), $e->getFile(), $e->getLine()]);
            // 'order_status'     => Order::STATUS_DELIVERY_FAILURE,
            $data = [
                'send_fail_reason' => $e->getMessage()
            ];
            /*if($sync_waybill_number == 3) {
                $data['order_status'] = Order::STATUS_DELIVERY_FAILURE;
            }*/
            Order::query()->where('id', $shopOrder->id)->update($data);
            throw new AccidentException($e->getMessage());
        }
    }

    /**
     * @param $package
     * @param null $orders
     * @param array $params
     * @return bool
     * @throws AccidentException
     */
    public function packageShipment($package, $orders = null, array $params = []): bool
    {
        if (empty($package->logisticsApply->way_bill_number)) return false;  // 运单号为空
        // 没有指定订单则交运包裹的所有订单
        if (empty($orders)) {
            $orders = $package->orders;
        }
        $deliverCount = 0;
        // 订单包裹交运
        foreach ($orders as $order) {
            try {
                $deliveryType = $this->shop->setting->delivery_type ?? 1;
                $lastMailTrackingNumber = $package->logisticsApply->last_mail_tracking_number;

                $notifyEmail = 0;
                $isDelivery = false;
                // 上传尾程单号
                if (!empty($lastMailTrackingNumber) && $deliveryType != ShopSetting::DELIVERY_HEAD_NOT_LAST) {
                    // shopify 是否发送邮件通知
                    if (in_array($deliveryType, [ShopSetting::DELIVERY_HEAD_AND_LAST, ShopSetting::DELIVERY_HEAD_NOT_EMAIL_AND_LAST, ShopSetting::DELIVERY_NOT_HEAD_AND_LAST])) {
                        $notifyEmail = 1;
                    }
                    $params = [
                        'tracking_number' => $lastMailTrackingNumber,
                        'notify_email' => $notifyEmail,
                        ...$params
                    ];
                    $this->platformService->packageShipment($package, $order, $params);
                    $isDelivery = true;
                } else {  //  上传头程单号
                    if ((empty($lastMailTrackingNumber) && $deliveryType != ShopSetting::DELIVERY_NOT_HEAD_AND_LAST) ||
                        (!empty($package->logisticsApply->way_bill_number) && $deliveryType == ShopSetting::DELIVERY_HEAD_NOT_LAST)) {
                        // shopify 是否发送邮件通知
                        if (in_array($deliveryType, [ShopSetting::DELIVERY_HEAD_AND_LAST, ShopSetting::DELIVERY_HEAD_NOT_LAST])) {
                            $notifyEmail = 1;
                        }
                        $params = [
                            'tracking_number' => $package->logisticsApply->way_bill_number,
                            'notify_email' => $notifyEmail,
                            ...$params
                        ];
                        $this->platformService->packageShipment($package, $order, $params);
                        $isDelivery = true;
                    }
                }

                if ($isDelivery) {
                    // 处理交运异常
                    (new OrderBaseService($order))->dealOrderAbnormal(ShopOrderAbnormal::DEAL_TYPE_DELIVERY_SUCCESS);

                    $order->shipment_time = now();
                    $order->is_shipping = Order::IS_SHIPPING_YES;
                    $order->save();
                    $deliverCount ++;
                } else {
                    info("订单{$order->order_id}不交运", ['lastMailTrackingNumber' => $lastMailTrackingNumber, 'deliveryType' => $deliveryType]);
                }

            } catch (PlatformShopException $e) {  // 自定义店铺错误
                (new OrderBaseService($order))->addOrderAbnormal(ShopOrderAbnormal::ABNORMAL_DELIVERY_FAILURE, $e->type . $e->getMessage());
                $this->shipmentFail($order, $package, $e->getMessage());
                throw new AccidentException($e->type . $e->getMessage(), Code::OPERATE_FAIL);
            } catch (Exception $e) { // 未定义的系统错误
                $this->shipmentFail($order, $package, $e->getMessage());
                (new OrderBaseService($order))->addOrderAbnormal(ShopOrderAbnormal::ABNORMAL_DELIVERY_FAILURE, '交运失败: ' . $e->getMessage());
                throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
            }
        }

        if ($deliverCount > 0) {
            $data = ['send_fail_reason' => ''];
            $data['is_shipping'] = Order::IS_SHIPPING_YES;
            $package->update($data);
        }
        return true;
    }

    /** 交运失败处理
     * @param $order
     * @param $package
     * @param $message
     * @return void
     */
    protected function shipmentFail($order, $package, $message)
    {
        $data = [
            'send_fail_reason' => $message
        ];
        $data['is_shipping'] = Order::IS_SHIPPING_NO;
        $package->update($data);
        $order->is_shipping = Order::IS_SHIPPING_NO;
        $order->save();
    }


    /** 写入刊登日志
     * @param $clientGoods
     * @param $status
     * @param $info
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    protected function addPublishLog($clientGoods, $status, $info, array $data = [])
    {
        $log = [
            'goods_id' => $clientGoods->id,
            'platform' => 'shopify',
            'info' => $info,
            'status' => $status,
            'ext' => $data
        ];
        return ClientGoodsPublishLog::query()->create($log);
    }


}
