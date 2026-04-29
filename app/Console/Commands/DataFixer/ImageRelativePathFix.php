<?php

namespace App\Console\Commands\DataFixer;

use App\Models\ClientGoods;
use App\Models\Goods;
use App\Models\RechargeApply;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class ImageRelativePathFix extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:image-fixed {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '替换老的图片域名';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('开始修复后台商品图片地址');
        Goods::query()->with('skus')->chunk(100, function ($goodsList) {
            foreach ($goodsList as $goods) {
                $oldUrl = $goods->cover_image;
                $goods->cover_image = $this->transform($goods->cover_image);
                if ($oldUrl == $goods->cover_image) {
                    $goods->save();
                    $mainImages = [];
                    foreach ($goods->main_images as $image) {
                        $mainImages[] = $this->transform($image);
                    }
                    $goods->main_images = $mainImages;
                    $goods->save();

                    foreach ($goods->skus as $sku) {
                        if (empty($sku->images[0])) continue;
                        $sku->images = [$this->transform($sku->images[0])];
                        $sku->save();
                    }
                }
            }
        });

        $this->info('开始修复客户端商品图片地址');
        ClientGoods::query()->with('skus')->chunk(100, function ($goodsList) {
            foreach ($goodsList as $goods) {
                $oldUrl = $goods->cover_image;
                $goods->cover_image = $this->transform($goods->cover_image);
                if ($oldUrl == $goods->cover_image) {
                    $goods->save();
                    $mainImages = [];
                    foreach ($goods->main_images as $image) {
                        $mainImages[] = $this->transform($image);
                    }
                    $goods->main_images = $mainImages;
                    $goods->save();

                    foreach ($goods->skus as $sku) {
                        if (empty($sku->images[0])) continue;
                        $sku->images = [$this->transform($sku->images[0])];
                        $sku->save();
                    }
                }
            }
        });

        $this->info('开始修复充值申请图片地址');
        RechargeApply::query()->chunk(100, function ($applies) {
            foreach ($applies as $apply) {

                if (!empty($apply->apply_images)) {
                    $apply_images = [];
                    foreach ($apply->apply_images as $image) {
                        $apply_images[] = $this->transform($image);
                    }
                    $apply->apply_images = $apply_images;
                }

                if (!empty($apply->confirm_images)) {
                    $confirm_images = [];
                    foreach ($apply->confirm_images as $image) {
                        $confirm_images[] = $this->transform($image);
                    }
                    $apply->confirm_images = $confirm_images;
                }

                if (!empty($apply->check_images)) {
                    $check_images = [];
                    foreach ($apply->check_images as $image) {
                        $check_images[] = $this->transform($image);
                    }
                    $apply->check_images = $check_images;

                    $apply->save();
                }
            }
        });
    }

    public function transform($url)
    {
        $domainList = [
            'api.jiazhuofulfillment.com',
            'dsp-api.dropioneer.com',
            'api.omgodropshipping.com'
        ];
        $transformDomain = 'prod-api.dropshipping.plus';
        $parsed = parse_url($url);
        if (isset($parsed['host'])) {
            $domain = $parsed['host'];
            if (in_array($domain, $domainList)) {
                return  str_replace($domain, $transformDomain, $url);
            }
        }
        return $url;
    }
}
