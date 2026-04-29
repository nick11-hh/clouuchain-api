<?php

namespace App\Jobs;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\PlatformShop\PlatformShopService;

/**
 * 同步店铺产品队列
 * Class SyncPlatformProduct
 * @package App\Jobs
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2025/1/18 17:25
 */
class SyncPlatformProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $shop;

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
            $shop = $this->shop;
            info('SyncPlatformProductJob', ['shop_name' => $shop->shop_name, 'shop_id' => $shop->id]);
            (new PlatformShopService($shop))->syncProductList();

        } catch (GuzzleException $e) {
            info('SyncPlatformProductJob-GuzzleException', [
                'msg' => [$e->getMessage(), $e->getFile(), $e->getLine()],
                'shop_name' => $shop->shop_name,
                'shop_id' => $shop->id
            ]);
        } catch (\Exception $e) {
            info('SyncPlatformProductJob-Exception', [
                'msg' => [$e->getMessage(), $e->getFile(), $e->getLine()],
                'shop_name' => $shop->shop_name,
                'shop_id' => $shop->id
            ]);
        }
    }
}
