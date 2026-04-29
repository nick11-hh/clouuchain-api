<?php

namespace App\Listeners\Tracking;

use App\Events\TrackingSubscribe;
use App\Models\ApiTrackingConfig;
use App\Models\Order;
use App\Models\Package;
use App\Services\ExpressCompanies\KD100Service;
use App\Services\ExpressCompanies\TrackingMoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class Subscribe implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * Handle the event.
     *
     * @param TrackingSubscribe $event
     * @return array|bool|int
     */
    public function handle(TrackingSubscribe $event)
    {
        info("当前公司ID[{$event->companyId}],单号[{$event->trackingNumber}]进入物流订阅");

        if ($event->type == ApiTrackingConfig::TYPE_KUAIDI_100) {
            try {
                $status = (new KD100Service(companyId: $event->companyId))
                    ->subscribe($event->expressCompanyCode, $event->trackingNumber);
            } catch (\Exception) {
                return 0;
            }

            $status && $this->packageSubscribed($event);
            return $status;
        } elseif ($event->type == ApiTrackingConfig::TYPE_51_TRACKING) {
            $status = (new TrackingMoreService(companyId: $event->companyId))->create($event->expressCompanyCode, $event->trackingNumber);
            $status && $this->orderSubscribed($event);
            return $status;
        }

        return true;
    }

    public function packageSubscribed(TrackingSubscribe $event)
    {
        $package = Package::query()
            ->where('company_id', $event->companyId)
            ->where('express_num', $event->trackingNumber)
            ->first();
        if (!$package) return;

        $package->update([
            'tracking_type' => $event->type,
            'third_tracking_status' => 'subscribed'
        ]);
    }


    public function orderSubscribed(TrackingSubscribe $event)
    {
        $order = Order::query()
            ->where('company_id', $event->companyId)
            ->where('logistics_sn', $event->trackingNumber)
            ->first();
        if (!$order) return;

        $order->update([
            'tracking_type' => $event->type,
            'third_tracking_status' => 'subscribed'
        ]);
    }
}
