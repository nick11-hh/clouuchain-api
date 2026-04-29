<?php

namespace App\Services\Shopify;

use App\Lib\Code;
use App\Models\ClientGoods;
use App\Models\ClientGoodsPublishLog;
use App\Models\PlatformProduct;
use App\Models\PlatformProductSku;
use App\Models\PlatformSkuMapping;
use App\Models\ShopModel;
use App\Models\ShopPlatformConfig;
use Carbon\Carbon;
use App\Exceptions\AccidentException;

class ProductService
{

    /**
     * @param $shop
     * @param $clientGoods
     * @return bool
     * @throws \Exception
     */
    public function publishClientGoods($shop, $clientGoods)
    {
        try {
            // 转换数据格式
            $product = $this->transformClientGoods($clientGoods);
            $data['product'] = $product;
            $requestService = new RequestService($shop);
            // 刊登
            $result = $requestService->publishProduct($data);
            if (!empty($result['error'])) {
                throw new AccidentException('刊登失败' . $result['error']['title'] ?? '未知原因', Code::OPERATE_FAIL);
            }
            $platformProduct = $result['product'];

            // 上传变种图片和库存
            $variant = collect($platformProduct['variants'])->keyBy('sku');
            $uploadImages = [];
            foreach ($clientGoods->skus as $sku) {
                if (empty($variant[$sku->sku_id])) continue;
                if (!empty($sku->images[0])) {
                    // 合并相同图片的变种id
                    if (isset($uploadImages[$sku['images'][0]])) {
                        $uploadImages[$sku->images[0]][] = $variant[$sku->sku_id]['id'];
                    } else {
                        $uploadImages[$sku->images[0]] = [$variant[$sku->sku_id]['id']];
                    }
                }
                // 追踪库存
                if (!$sku->quantity) continue;
                $requestService->setInventoryItems($variant[$sku->sku_id]['inventory_item_id'], ['tracked' => true]);
                $this->setInventoryQuantity($shop, $variant[$sku->sku_id]['inventory_item_id'], $sku->quantity);
            }
            //上传变种图片
            foreach ($uploadImages as $src => $variantIds) {
                $requestService->uploadProductImages($platformProduct['id'], [
                    'variant_ids' => $variantIds,
                    'src' => $src
                ]);
            }
            // 更新推送状态
            $clientGoods->status = ClientGoods::STATUS_PUBLISHED;
            $clientGoods->save();

            // 日志
            $info = 'Shopify publish success, Product ID: ' . $platformProduct['id'];
            $this->addPublishLog($clientGoods, ClientGoodsPublishLog::PUBLISH_SUCCESS, $info);
            // 生成sku采购映射
            $this->generateSkuPurchaseMapping($platformProduct, $clientGoods);
            // 刊登完成后同步到店铺产品
            $this->syncProductInfo($platformProduct['id'], $shop->id);
            return true;
        } catch (\Exception $e) {
            // 日志
            info('刊登失败', [$e->getMessage() . $e->getFile() . $e->getLine()]);
            $message = $e->getMessage();
            $msg = '';
            if (preg_match('/Exceeded maximum number of variants allowed/', $message)) {
                $msg = 'The current product specification exceeds the maximum allowed number of variants. Please reduce the quantity of product specifications .';
            }
            $info = 'Shopify publish fail.  reason:' . __($msg . $message);
            $this->addPublishLog($clientGoods, ClientGoodsPublishLog::PUBLISH_ERROR, $info);
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    /** 同步店铺产品
     * @param $shop
     * @return void
     * @throws \Exception
     */
    public function syncShopProduct($shop)
    {
        $requestService = new RequestService($shop);
        $list = $requestService->getProductList([]);
        foreach ($list as $product) {
            $this->createOrUpdateProduct($shop, $product);
        }
        $productIds = array_column($list, 'id');
        // 删除已经不存在的产品
        PlatformProduct::query()
            ->where('shop_id', $shop->id)
            ->whereNotIn('product_id', $productIds)
            ->delete();
    }

    /** 同步单个产品信息
     * @param $platformProduct
     * @return void
     * @throws \Exception
     */
    public function syncProductInfo($productId, $shopId)
    {
        $shop = ShopModel::query()->findOrFail($shopId);
        $requestService = new RequestService($shop);
        $productInfo = $requestService->getProductInfo($productId);
        $this->createOrUpdateProduct($shop, $productInfo);
    }

    /** 删除线上产品
     * @param $platformProduct
     * @return void
     * @throws \Exception
     */
    public function deleteProduct($platformProduct)
    {
        $shop = ShopModel::query()->findOrFail($platformProduct->shop_id);
        $requestService = new RequestService($shop);
        $result = $requestService->deleteProduct($platformProduct->product_id);
        if (empty($result)) {
            $platformProduct->delete();
        }
    }


    public function getCollect($shop)
    {
        $requestService = new RequestService($shop);
        $collect = $requestService->getProductImages(8050095489252);
    }

    /** 创建或更新产品
     * @param $shop
     * @param $product
     * @return void
     */
    protected function createOrUpdateProduct($shop, $product)
    {
        unset($platformProduct);
        $platformProduct = PlatformProduct::query()
            ->where('shop_id', $shop->id)
            ->where('product_id', $product['id'])
            ->first();
        if (empty($platformProduct)) {
            $platformProduct = new PlatformProduct();
            $platformProduct->custom_id = $shop->customer_id;
            $platformProduct->shop_id = $shop->id;
            $platformProduct->shop_type = 'shopify';
            $platformProduct->product_id = $product['id'];
        }
        $platformProduct->product_name = $product['title'];
        $platformProduct->product_type = $product['product_type'];
        $platformProduct->tags = $product['tags'];
        $platformProduct->status = $product['status'];
        $platformProduct->options = $product['options'];
        $platformProduct->images = $product['images'];
        $platformProduct->detail = $product['body_html'];
        $platformProduct->published_at = Carbon::parse($product['created_at'])->toDateTimeString();
        $platformProduct->save();
        $skuIds = array_column($product['variants'], 'id');
        foreach ($product['variants'] as $variant) {
            $sku = PlatformProductSku::query()
                ->where('product_id', $platformProduct->id)
                ->where('platform_sku_id', $variant['id'])
                ->first();
            if (empty($sku)) {
                $sku = new PlatformProductSku();
                $sku->product_id = $platformProduct->id;
                $sku->platform_product_id = $platformProduct->product_id;
                $sku->platform_sku_id = $variant['id'];
            }
            $sku->sku = $variant['sku'];
            $sku->barcode = $variant['barcode'];
            $sku->title = $variant['title'];
            $sku->option = $variant['option1'];
            $sku->price = $variant['price'];
            $sku->inventory_quantity = $variant['inventory_quantity'];
            $sku->save();
        }
        PlatformProductSku::query()
            ->where('product_id', $platformProduct->id)
            ->whereNotIn('platform_sku_id', $skuIds)
            ->delete();
    }

    /**
     * @param $clientGoods
     * @return array
     */
    protected function transformClientGoods($clientGoods)
    {
        $options = [];
        foreach ($clientGoods->options as $option) {
            $specs = array_column($option['specs'], 'name');
            $options[] = [
                'name' => $option['name'],
                'values' => $specs
            ];
        }
        $variants = [];
        foreach ($clientGoods->skus as $sku) {
             $skuData = [
                 'sku' => $sku->sku_id,
                 'price' => $sku->sale_price,
                 'compare_at_price' => $sku->original_price,
                 'cost' => $sku->cost_price,
//                 'inventory_quantity' => $sku->quantity ?? 0,
                 'image_id' => null
            ];
             foreach ($sku->spec_info as $key => $skuSpec) {
                 $skuData['option' . ($key + 1)] = $skuSpec['value'];
             }
            $variants[] = $skuData;
        }
        $images = array_map(function ($item) {
            return ['src' => $item];
        }, $clientGoods->main_images);
        return [
            'title' => $clientGoods->goods_name,
            'body_html' => $clientGoods->detail,
            'vendor' => 'Burton',
            'product_type' => $clientGoods->category_name,
            'status' => 'active',
            'images' => $images,
            'options' => $options,
            'variants' => $variants,
        ];
    }

    /**
     * @param $clientGoods
     * @param $status
     * @param $info
     * @param $data
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    public function addPublishLog($clientGoods, $status, $info, $data = [])
    {
        $log = [
            'goods_id' => $clientGoods->id,
            'platform' => 'shopify',
            'info' => $info,
            'status' => $status,
            'ext' => $data
        ];
        return ClientGoodsPublishLog::query()->create($log);
    }

    /** 生成平台产品sku采购映射
     * @param $product
     * @param $clientGoods
     * @return void
     */
    public function generateSkuPurchaseMapping($product, $clientGoods)
    {
        $data = [];
        $skuList = $clientGoods->skus->keyBy('sku_id');
        foreach ($product['variants'] as $variant) {
            $data[] = [
                'custom_id' => $clientGoods->custom_id,
                'product_id' => $product['id'],
                'platform_sku_id' => $variant['id'],
                'custom_sku_id' => $variant['sku'],
                'purchase_platform' => $clientGoods->purchase_platform ?? '',
                'purchase_url' => $clientGoods->source_url ?? '',
                'purchase_product_id' => $clientGoods->purchase_product_id ?? '',
                'purchase_spec_id' => $skuList[$variant['sku']]['purchase_spec_id'] ?? '',
            ];
        }
        PlatformSkuMapping::query()->insert($data);
    }

    public function setInventoryQuantity($shop, $inventoryItemId, $quantity)
    {
        $requestService = new RequestService($shop);
        $locationList = $requestService->getInventoryLocations();
        $locationInfo = null;
        $applicationName = ShopPlatformConfig::getPlatformApplicationName('shopify');
        foreach ($locationList['locations'] as $location) {
            if ($location['name'] === $applicationName) {
                $locationInfo = $location;
                break;
            }
        }
        if (empty($locationInfo)) return;
        $requestService->connectInventory($locationInfo['id'], $inventoryItemId);
        $requestService->setInventoryLevelAdjust($locationInfo['id'], $inventoryItemId, $quantity);
    }

}
