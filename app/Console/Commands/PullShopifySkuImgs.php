<?php

namespace App\Console\Commands;

use App\Lib\Platform;
use App\Models\OrderLineItem;
use App\Models\ShopModel;
use App\Services\PlatformShop\Platform\Shopify\ShopifyService;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class PullShopifySkuImgs extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:pull-shopify-sku-img {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '拉取shopify订单sku图片';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $shops = ShopModel::query()->where(['platform' => Platform::SHOPIFY, 'status' => ShopModel::STATUS_AUTH, 'enable' => ShopModel::ENABLE])->get();
            $shops->each(function ($shop) {
                $shopifyService = new ShopifyService($shop);

                OrderLineItem::query()->whereHas('shopOrder', function ($query) use($shop) {
                    $query->where('shop_id', $shop->id);
                })->where(function($query) {
                    $query->where('imgs', '[]')->orWhere('imgs', '')->orWhereNull('imgs');
                })->chunk(1000, function ($items) use ($shopifyService) {
                    $items->each(function ($item) use($shopifyService) {
                        if($item->product_id) {
                            $imgs = $shopifyService->getOrderItemImages($item->toArray());
                            $item->imgs = $imgs;
                            $item->save();
                        }
                    });
                });


            });

        } catch (\Exception $e) {
            logger('获取shopify订单图片失败：'.$e->getMessage());
        }

        $this->info('拉取订单图片完毕！');
    }
}
