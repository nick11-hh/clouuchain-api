<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\PlatformShop\PlatformShopService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FulfillmentOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $id;

    public $backoff = 300;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->id = $id;
        $this->queue = 'order_shipment';
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        $shopOrder = Order::query()
            ->with([
                'allLineItems',
                'shop',
                'custom:id,custom_name,custom_email,main_user_id',
                'custom.mainUser:id,custom_id,username',
                'expressLine:id,name,en_name',
                'logisticsApply:id,order_id,way_bill_number,fulfillment_express_line,remark'
            ])
            ->find($this->id);
        info('履约发货订单', [$shopOrder->order_id]);
        if (empty($shopOrder->shop)) {
            Order::query()->where('id', $shopOrder->id)->update(['send_fail_reason' => '店铺不存在或者已解绑']);
        }
        $platformShopService = new PlatformShopService($shopOrder->shop);
        $platformShopService->orderShipment($shopOrder);
    }
}
