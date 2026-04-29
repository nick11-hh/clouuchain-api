<?php

namespace App\Services\Base;

use App\Jobs\FulfillmentOrderJobV2;
use App\Lib\Platform;
use App\Models\LogisticsTracking;
use App\Models\Order;
use App\Models\Package;
use App\Models\ShopSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PackageBaseService
{
    protected $package;

    public function __construct($package)
    {
        $this->package = $package;
    }

    /** 订单交运，解决shopify 429请求频繁问题
     * @param bool $sync 同步或异步
     * @param int $delay  延迟
     * @params bool $manual 是否是手动交运
     * @return \Illuminate\Foundation\Bus\PendingClosureDispatch|\Illuminate\Foundation\Bus\PendingDispatch|mixed|true
     */
    public function packagePlatformDelivery(bool $sync = false, int $delay = 4, $manual = false, $orderId = 0)
    {
        // 非手动交运需要根据店铺配置判断是否交运
        if (!$manual) {
            $shop = $this->package->orders[0]->shop;
            if (!empty($shop)) {
                $shopSetting = ShopSetting::query()->where('shop_id', $shop->id)->first();
                if (!($shopSetting->auto_shop_delivery ?? 1)) {
                    return false;
                }
            }
        }
        if ($sync) { // 同步执行
            return dispatch_sync(new FulfillmentOrderJobV2($this->package->id));
        }
        if ($this->package->orders[0]->platform != Platform::SHOPIFY)  {  // 不是shopify订单
            return dispatch(new FulfillmentOrderJobV2($this->package->id))->delay($delay);
        }

        // shopify 订单
        $delayTime = Carbon::now()->addSeconds($delay); //延迟时间
        $redisKey = 'shopify_shop_transport_time_' . $this->package->orders[0]->shop->id;
        $lastTime = Cache::get($redisKey);
        if (!empty($lastTime)) {  // 上次执行时间
            // 延迟两秒
            $delayTime = Carbon::parse($lastTime)->addSeconds($delay);
        }

        dispatch(new FulfillmentOrderJobV2($this->package->id, $orderId))->delay($delayTime);
        Cache::set($redisKey, $delayTime, 3600);
        return true;
    }

    /** 更新订单和包裹的物流状态
     * @return void
     */
    public function logisticsStatusUpdate()
    {
        if ($this->package->tracking_status == LogisticsTracking::STATUS_DELIVERED) {
            $this->package->status = Package::STATUS_DELIVERED;
            $this->package->save();
            $this->package->orders->each(function ($order) {
                $order->order_status = Order::STATUS_DELIVERED;
                $order->save();
            });
        }
    }

}
