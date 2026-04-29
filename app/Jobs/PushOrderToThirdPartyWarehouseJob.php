<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\ThirdPartyWarehouseConfig;
use App\Services\ThirdPartyWarehouse\ThirdPartyWarehouseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PushOrderToThirdPartyWarehouseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $orderId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        $config = ThirdPartyWarehouseConfig::getConfig();
        if (empty($config)) return;
        $order = Order::query()->where('order_id', $this->orderId)->first();
        if (empty($order)) return ;
        $service = new ThirdPartyWarehouseService($config);
        $service->pushOrderToWarehouse($order);
    }
}
