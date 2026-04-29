<?php

namespace App\Jobs;

use App\Models\ShopModel;
use App\Services\Client\ShopService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteShopDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $shop;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($shop)
    {
        $this->shop = $shop;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $shopService = new ShopService(new ShopModel());

            //删除店铺订单
            $shopService->deleteShopOrders($this->shop);
        } catch (\Exception $e) {
            info('DeleteShopDataJob-Exception', [
                'msg'       => [$e->getMessage(), $e->getFile(), $e->getLine()],
                'shop_name' => $this->shop->shop_name,
                'shop_id'   => $this->shop->id
            ]);
        }

    }
}
