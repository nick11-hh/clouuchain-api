<?php

namespace App\Jobs;

use App\Models\ClientGoods;
use App\Models\ShopModel;
use App\Services\PlatformShop\PlatformShopService;
use App\Services\Shopify\ProductService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishGoodsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $shopIds;

    protected $clientGoodsIds;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($shopIds, $clientGoodsIds)
    {
        $this->shopIds = $shopIds;
        $this->clientGoodsIds = $clientGoodsIds;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $shopList = ShopModel::query()->whereIn('id', $this->shopIds)->get();
        $clientGoods = ClientGoods::query()->whereIn('id', $this->clientGoodsIds)->get();
        foreach ($shopList as $shop) {
            $platformShopService = new PlatformShopService($shop);
            foreach ($clientGoods as $goods) {
                try {
                    $platformShopService->productPublish($goods);
                } catch (\Exception $e) {
                    info('产品刊登失败', ['shop_id' => $shop->id, 'goods_id' => $goods->id]);
                }
            }
        }
    }
}
