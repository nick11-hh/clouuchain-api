<?php

namespace App\Services\Client;

use App\Models\ClientGoods;
use App\Models\ClientGoodsSku;
use App\Models\CustomConfig;
use App\Models\SystemConfig;
use App\Services\Base\SystemConfigService;
use App\Services\Collect\CollectService;
use App\Services\Collect\Platform\Y1688\RequestApi;
use App\Services\Translation\TranslationService;
use Exception;
use Illuminate\Support\Facades\DB;

class Y1688Service
{
    /**
     * @param $params
     * @return array|mixed
     * @throws Exception
     */
    public function getProductList($params)
    {

        $language = request()->header('language');
        $service = new CollectService('1688', $language);
        $data = $service->getProductList($params);
        // 蒙古语翻译
        if ($language === 'mn_MN') {
            $data['data'] = $this->translateProductList($data['data']);
        }
        // 获取系统配置
        $alibabaPrice = SystemConfigService::getConfigValue(SystemConfig::ALIBABA_PRICE);
        foreach ($data['data'] as &$goods) {
            $goods['old_price'] = $goods['price'];
            // 通过系统配置计算价格
            $price = $goods['price'] * $alibabaPrice['discount'] + $alibabaPrice['fixed_price'];
            $goods['price'] = round($price, 2);
        }
        return $data;
    }

    /**
     * @return array|mixed
     * @throws Exception
     */
    public function imageSearch($params)
    {
        $language = request()->header('language');
        $service = new CollectService('1688', $language);
        $data = $service->productImageSearch($params);

        // 蒙古语翻译
        if ($language === 'mn_MN') {
            $data['data'] = $this->translateProductList($data['data']);
        }
        // 获取系统配置
        $alibabaPrice = SystemConfigService::getConfigValue(SystemConfig::ALIBABA_PRICE);
        foreach ($data['data'] as &$goods) {
            // 通过系统配置计算价格
            $price = $goods['price'] * $alibabaPrice['discount'] + $alibabaPrice['fixed_price'];
            $goods['price'] = round($price, 2);
        }
        return $data;

    }

    /**
     * @param $id
     * @return array|mixed
     * @throws Exception
     */
    public function detail($id, $params)
    {
        $language = request()->header('language');
        $service = new CollectService('1688', $language);
        $data = $service->getProductDetail($id);

        // 蒙古语翻译
        if ($language === 'mn_MN') {
            $data= $this->translateProductDetail($data);
        }

        $data['skus'] = $data['sku_list'];
        unset($data['sku_list']);

        // 获取系统配置
        $alibabaPrice = SystemConfigService::getConfigValue(SystemConfig::ALIBABA_PRICE);
        $data['old_price'] = $data['price'];

        $price = $data['price'] * $alibabaPrice['discount'] + $alibabaPrice['fixed_price'];
        // 通过系统配置计算价格
        $data['price'] = round($price, 2);
        foreach ($data['skus'] as &$sku) {
            $salePrice = $sku['sale_price'] * $alibabaPrice['discount'] + $alibabaPrice['fixed_price'];
            // 通过系统配置计算价格
            $sku['sale_price'] = round($salePrice, 2);
            $sku['weight'] = 1000;
        }
        return $data;
    }

    /***
     * @param $id
     * @return mixed
     * @throws Exception
     */
    public function claim($id, $isSaveClientGoods = true,  $transformPrice = true)
    {
        $language = request()->header('language');
        $service = new CollectService('1688', $language);
        $detail = $service->getProductDetail($id, $transformPrice);

        if ($isSaveClientGoods) {
            return $this->saveClientGoods($detail);
        }

        return $detail;
    }

    /**
     * @param $params
     * @return mixed
     */
    protected function saveClientGoods($params)
    {
        $params['spu'] = $params['goods_id'];
        $params['purchase_platform'] = $params['collect_platform'];
        $params['source_url'] = $params['collect_url'];
        $params['purchase_product_id'] = $params['goods_id'];
        return DB::transaction(function () use ($params) {
            $goodsData = ClientGoods::init($params);
            $goods = ClientGoods::query()->create($goodsData);
            $minPrice = 0;
            $defaultOriginalPriceRatio = 0;
            $defaultCompareOriginalPriceRatio = 0;
            $configData = CustomConfig::query()->where('custom_id', getCustomId())->first();
            if ($configData) {
                $defaultOriginalPriceRatio = $configData['default_original_price_ratio'] ?: 0;
                $defaultCompareOriginalPriceRatio = $configData['default_compare_original_price_ratio'] ?: 0;
            }

            $shopifyReviewMode = SystemConfigService::getConfigValue(SystemConfig::SHOPIFY_APP_REVIEW_MODE);
            foreach ($params['sku_list'] as $sku) {
                $sku['sale_price'] = $defaultOriginalPriceRatio > 0 ? $sku['sale_price'] * $defaultOriginalPriceRatio : $sku['sale_price'];
                $sku['original_price'] = $defaultCompareOriginalPriceRatio > 0 ? $sku['compare_price'] * $defaultCompareOriginalPriceRatio : $sku['compare_price'];

                //商品最低价格
                if ($minPrice == 0 || $minPrice > $sku['sale_price']) $minPrice = $sku['sale_price'];

                $sku['goods_id'] = $goods->id;
                $sku['purchase_spec_id'] = $sku['spec_id'];
                $skuData = ClientGoodsSku::init($sku, $shopifyReviewMode);
                ClientGoodsSku::query()->create($skuData);
            }
            $defaultOriginalPriceRatio > 0 && $minPrice = $minPrice * $defaultOriginalPriceRatio;
            $goods->goods_lowest_price = $minPrice;
            $goods->save();
            return $goods->id;
        });
    }

    public function getAuthUrl(): string
    {
        $service = new GlobalOpenService();
        return $service->alibabaAuthorize();
    }

    public function auth()
    {
        $service = new GlobalOpenService();
        return $service->generateAccessToken();
    }

    /**
     * 创建采购订单
     * @return void
     */
    public function createCrossOrder()
    {
        $service = new GlobalOpenService();
        return $service->createCrossOrder();
    }

    public function getProductFreight($data)
    {
        $params = [
            'productFreightQueryParamsNew' => json_encode(
                [
                    'offerId' => $data['offerId'],
                    'toProvinceCode' => 330000,
                    'toCityCode' => 330100,
                    'toCountryCode' => 330108,
                    'totalNum' => $data['sum'],
                ])
        ];

        $service = new GlobalOpenService();
        return $service->getProductFreight($params);
    }

    protected function translateProductList($productList)
    {
        $needTranslateList = array_column($productList, 'name');
        $translateList = (new TranslationService('mn'))->translation($needTranslateList);
        foreach ($productList as &$product) {
            $product['name'] = $translateList[$product['name']] ?? $product['name'];
        }
        return $productList;
    }


    protected function translateProductDetail($productDetail)
    {
        $needTranslateList = [];
        $needTranslateList[] = $productDetail['goods_name'];
        foreach ($productDetail['options'] as $option) {
            $needTranslateList[] = $option['name'];
            foreach ($option['specs'] as $spec) {
                $needTranslateList[] = $spec['name'];
            }
        }
        foreach ($productDetail['sku_list'] as $sku) {
            $needTranslateList[] = $sku['spec_name'];
        }
        $translateList = (new TranslationService('mn'))->translation($needTranslateList);
        $productDetail['goods_name'] = $translateList[$productDetail['goods_name']] ?? $productDetail['goods_name'];
        foreach ($productDetail['options'] as &$option) {
            $option['name'] = $translateList[$option['name']] ?? $option['name'];
            foreach ($option['specs'] as &$spec) {
                $spec['name'] = $translateList[$spec['name']] ?? $spec['name'];
            }
        }
        foreach ($productDetail['sku_list'] as &$sku) {
            $sku['spec_name'] = $translateList[$sku['spec_name']] ?? $sku['spec_name'];
            foreach ($sku['spec_info'] as &$spec) {
                $spec['name'] = $translateList[$spec['name']] ?? $spec['name'];
                $spec['value'] = $translateList[$spec['value']] ?? $spec['value'];
            }
        }
        return $productDetail;
    }
}
