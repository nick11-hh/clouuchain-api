<?php

namespace App\Services\Client;

use App\Helper\CurrencyConverter;
use App\Models\ClientGoods;
use App\Models\ClientGoodsSku;
use App\Models\CustomConfig;
use App\Models\Goods;
use App\Models\GoodsCategory;
use App\Models\GoodsSku;
use App\Models\SystemConfig;
use App\Services\Base\SystemConfigService;
use Illuminate\Support\Facades\DB;

class AdminGoodsService extends BaseService
{
    public $filterRules = [
        'goods_name' => ['like', 'goods_name'],
        'spu' => ['=', 'spu'],
        'goods_type' => ['=', 'goods_type'],
        'packing_materials_type' => ['=', 'packing_materials_type'],
    ];

    public function __construct()
    {
        $this->model = new Goods();
        $this->skuModel = new GoodsSku();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    /** 热销产品分类
     */
    public function getCategoryList()
    {
        $name = $this->formData['name'] ?? '';
        $status = $this->formData['status'] ?? '';
        $isRecommend = $this->formData['is_recommend'] ?? 0;
        $query = GoodsCategory::query()->with(['categories' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }]);
        $query->where('parent_id', 0);
        $query->when($name, function ($query) use ($name) {
            return $query->where('name', $name);
        });
        $query->when($status, function ($query) use ($status) {
            return $query->where('status', $status);
        });
        $query->when($isRecommend == 1, function ($query) use ($isRecommend) {
            return $query->whereNotNull('recommended_time');
        });

        return $query->get();
    }

    /** 热销产品列表
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getGoodsList()
    {
        $this->query->where('status', Goods::STATUS_ENABLE)->where('is_hot', 1);

        // 处理分类筛选逻辑
        $categoryId = $this->formData['category_id'] ?? '';
        if ($categoryId) {
            $category = GoodsCategory::find($categoryId);
            if ($category) {
                if ($category->parent_id == 0) {
                    // 如果是一级分类，获取该分类及其所有子集分类下的商品
                    $categoryIds = $this->getAllChildCategoryIds($categoryId);
                    $categoryIds[] = (int)$categoryId;

                    // 确保 categoryIds 不为空且包含有效数据
                    if (!empty($categoryIds)) {
                        $this->query->whereIn('category_id', $categoryIds);
                    } else {
                        // 如果没有子分类，只查询当前一级分类
                        $this->query->where('category_id', $categoryId);
                    }
                } else {
                    // 如果是子集分类，只获取该子分类下的商品
                    $this->query->where('category_id', $categoryId);
                }
            }
        }

        $this->query->with(['skus', 'hasSelect'])->latest();
        $list = parent::index();

        $currencyConverter = new CurrencyConverter();
        $list->map(function ($item) use ($currencyConverter) {
            $priceArray = $item->skus->pluck('sale_price')->toArray();
            if (!empty($priceArray)) {
                $item->min_price = min($priceArray);
//                $item->max_price = max($priceArray);
            } else {
                $item->min_price = 0;
                $item->max_price = 0;
            }

            $item->min_price = $currencyConverter->reversedCurrenciesExchange($item->min_price);
//            $item->max_price = $currencyConverter->reversedCurrenciesExchange($item->max_price);

        });
        return $list;
    }

    /** 热销产品详情
     * @param $id
     * @param string $spu
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|null
     */
    public function getGoodsDetail($id, string $spu = '')
    {
        if ($id > 0) {
            $res = $this->model::query()->with(['skus', 'category', 'logistics'])->findOrFail($id);
        } else {
            $res = $this->model::query()->with(['skus', 'category', 'logistics'])->where('spu', $spu)->firstOrFail();
        }

//        $currencyConverter = new CurrencyConverter();
//        $res->skus->each(function ($item) use ($currencyConverter) {
//            $item->purchase_price = $currencyConverter->reversedCurrenciesExchange($item->purchase_price);
//            $item->sale_price = $currencyConverter->reversedCurrenciesExchange($item->sale_price);
//        });

        //获取商品一口价的配置
        $res->goods_one_price =  SystemConfigService::getConfigValue(SystemConfig::ORDER_ONE_PRICE);

        return $res;
    }

    /** 将热销产品加入到我的产品库
     * @param $id
     * @return bool
     */
    public function addToClientGoods($goods)
    {
        $data = $this->transformGoodsData($goods);
        return DB::transaction(function () use ($data) {
            $goodsData = ClientGoods::init($data);
            $clientGoods = ClientGoods::query()->create($goodsData);
            $minPrice = 0;
            $defaultOriginalPriceRatio = 0;
            $defaultCompareOriginalPriceRatio = 0;
            $configData = CustomConfig::query()->where('custom_id', getCustomId())->first();
            if ($configData) {
                $defaultOriginalPriceRatio = $configData['default_original_price_ratio'] ?: 0;
                $defaultCompareOriginalPriceRatio = $configData['default_compare_original_price_ratio'] ?: 0;
            }
            $shopifyReviewMode = SystemConfigService::getConfigValue(SystemConfig::SHOPIFY_APP_REVIEW_MODE);
            foreach ($data['skus'] as $sku) {
                if ($minPrice == 0 || $minPrice > $sku['sale_price']) $minPrice = $sku['sale_price'];

                $sku['goods_id'] = $clientGoods->id;
                $defaultOriginalPriceRatio > 0 && $sku['sale_price'] = $sku['sale_price'] * $defaultOriginalPriceRatio;
                $defaultCompareOriginalPriceRatio > 0 && $sku['original_price'] = $sku['original_price'] * $defaultCompareOriginalPriceRatio;
                $skuData = ClientGoodsSku::init($sku, $shopifyReviewMode);
                ClientGoodsSku::query()->create($skuData);
            }
            $defaultOriginalPriceRatio > 0 && $minPrice = $minPrice * $defaultOriginalPriceRatio;
            $clientGoods->goods_lowest_price = $minPrice;
            $clientGoods->save();
            return $clientGoods->id;
        });
    }

    public function transformGoodsData($goods)
    {
        $data = [
            'spu' => $goods->spu,
            'goods_name' => $goods->goods_name,
            'category_id' => 0,
            'category_name' => $goods->category->name ?? '',
            'brand' => $goods->brand,
            'unit' => $goods->unit,
            'source_url' => $goods->purchase_url ?? '',
            'cover_image' => $goods->cover_image,
            'main_images' => $goods->main_images,
            'options' => $goods->options,
            'props' => $goods->props,
            'detail' => $goods->detail,
            'purchase_platform' => $goods->purchase_platform,
            'purchase_product_id' => $goods->purchase_product_id,
            'skus' => [],
            'goods_type' => $goods->goods_type ?? 1, //商品类型 1-产品 2-包材
        ];
        foreach ($goods->skus as $sku) {
            $data['skus'][] = [
                'sku_id' => $sku->sku_id,
                'spec_name' => $sku->spec_name,
                'spec_info' => $sku->spec_info,
                'sale_price' => $sku->sale_price,
                'original_price' => $sku->purchase_price,
                'images' => $sku->images,
                'quantity' => $sku->quantity ?? 0,
                'status' => $sku->status,
                'purchase_spec_id' => $sku->purchase_spec_id,
            ];
        }
        return $data;
    }

    /**
     * 递归获取所有子分类 ID
     */
    private function getAllChildCategoryIds($categoryId)
    {
        $childCategories = GoodsCategory::where('parent_id', $categoryId)->get();
        $categoryIds = [];

        foreach ($childCategories as $child) {
            $categoryIds[] = $child->id;
            // 递归获取子分类的子分类
            $childCategoryIds = $this->getAllChildCategoryIds($child->id);
            $categoryIds = array_merge($categoryIds, $childCategoryIds);
        }

        return $categoryIds;
    }

}
