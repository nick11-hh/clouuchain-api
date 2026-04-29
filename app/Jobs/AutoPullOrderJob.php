<?php

namespace App\Jobs;

use App\Services\PlatformShop\PlatformShopService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoPullOrderJob implements ShouldQueue
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
        $this->queue = 'sync_order';
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
            info('AutoPullOrderJob', ['shop_name' => $this->shop->shop_name, 'shop_id' => $this->shop->id]);
            (new PlatformShopService($this->shop))->syncOrderList();

        } catch (GuzzleException $e) {
            info('AutoPullOrderJob-GuzzleException', [
                'msg'       => [$e->getMessage(), $e->getFile(), $e->getLine()],
                'shop_name' => $this->shop->shop_name,
                'shop_id'   => $this->shop->id
            ]);
        } catch (\Exception $e) {
            info('AutoPullOrderJob-Exception', [
                'msg'       => [$e->getMessage(), $e->getFile(), $e->getLine()],
                'shop_name' => $this->shop->shop_name,
                'shop_id'   => $this->shop->id
            ]);
        }

    }
}
