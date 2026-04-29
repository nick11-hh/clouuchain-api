<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Package;
use App\Models\ShopOrderAbnormal;
use App\Services\Base\OrderBaseService;
use App\Services\PlatformShop\PlatformShopService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FulfillmentOrderJobV2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $packageId;

    public $orderId;

    public $backoff = 300;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($packageId, $orderId = 0)
    {
        $this->packageId = $packageId;
        $this->orderId = $orderId;
        $this->queue = 'order_shipment';
    }

    /**
     * Execute the job.
     *
     * @return false
     * @throws Exception
     */
    public function handle()
    {
        $package = Package::query()->with('orders')->where('status', '!=', Package::STATUS_CANCELED)->find($this->packageId);
        if (empty($this->orderId)) {
            $orders = $package->orders;
        } else {
            $orders = Order::query()->where('id', $this->orderId)->get();
        }
        if (empty($orders[0]) || empty($orders[0]->shop)) {
            $package->update(['send_fail_reason' => '店铺不存在或者已解绑']);
        }
        $platformShopService = new PlatformShopService($orders[0]->shop);
        $platformShopService->packageShipment($package, $orders);
    }
}
