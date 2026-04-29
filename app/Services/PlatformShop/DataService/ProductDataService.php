<?php

namespace App\Services\PlatformShop\DataService;

use App\Models\PlatformProduct;
use App\Models\PlatformProductSku;

class ProductDataService
{
    public function saveProduct($shop, $product)
    {
        $platformProduct = PlatformProduct::query()->where('product_id', $product['product_id'])->first();
        if (empty($platformProduct)) {
            $product['custom_id'] = $shop->customer_id;
            $product['shop_id'] = $shop->id;
            $product['shop_type'] = $shop->platform;
            $productData = PlatformProduct::init($product);
            $platformProduct = PlatformProduct::query()->create($productData);
        } else {
            $productData = PlatformProduct::init($product, 2);
            $platformProduct->update($productData);
        }
        $skuIds = array_column($product['skus'], 'platform_sku_id');
        foreach ($product['skus'] as $sku) {
            $platformSku = PlatformProductSku::query()
                ->where('product_id', $platformProduct->id)
                ->where('platform_sku_id', $sku['platform_sku_id'])
                ->first();
            if (empty($platformSku)) {
                $sku['product_id'] = $platformProduct->id;
                $sku['platform_product_id'] = $product['product_id'];
                $skuData = PlatformProductSku::init($sku);
                $platformSku = PlatformProductSku::query()->create($skuData);
            } else {
                $skuData = PlatformProductSku::init($sku, 2);
                $platformSku->update($skuData);
            }
        }
        PlatformProductSku::query()
            ->where('product_id', $platformProduct->id)
            ->whereNotIn('platform_sku_id', $skuIds)
            ->delete();
        return $platformProduct;
    }
}
