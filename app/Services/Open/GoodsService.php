<?php
namespace App\Services\Open;

use App\Models\Goods;
use App\Models\GoodsSku;
use App\Models\GoodsCategory;
use App\Models\SystemConfig;
use App\Helper\CurrencyConverter;
use App\Services\Base\SystemConfigService;

class GoodsService extends BaseService
{
    /**
     * @var array[]
     */
    public $filterRules = [
        'goods_name' => ['like', 'goods_name'],
        'spu' => ['=', 'spu'],
        'category_id' => ['=', 'category_id'],
        'goods_type' => ['=', 'goods_type'],
        'packing_materials_type' => ['=', 'packing_materials_type'],
    ];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->model = new Goods();
        $this->skuModel = new GoodsSku();
        $this->formData = request()->all();
        $this->query = $this->model->query();
        $this->setFilterRules();
    }

    /**
     * 产品分类
     * @return \Illuminate\Database\Eloquent\Builder[]|
     * @return \Illuminate\Database\Eloquent\Collection
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

    /**
     * 热销产品列表
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getGoodsList()
    {
        $category_id = $this->formData['category_id'] ?? '';
        $start_time = $this->formData['start_time'] ?? '';
        $end_time = $this->formData['end_time'] ?? '';
        $time_type = $this->formData['time_type'] ?? '';
        $query = $this->query->where('status', Goods::STATUS_ENABLE)->where('is_hot', 1);
        if ($category_id) {
            $category = GoodsCategory::find($category_id);
            if ($category) {
                if ($category->parent_id == 0) {
                    // 如果是一级分类，获取该分类及其所有子集分类下的商品
                    $categoryIds = $this->getAllChildCategoryIds($category_id);
                    $categoryIds[] = $category_id;
                    $query->whereIn('category_id', $categoryIds);
                } else {
                    // 如果是子集分类，只获取该子分类下的商品
                    $query->where('category_id', $category_id);
                }
            }
        }
        if ($start_time && $end_time && $time_type) {
            switch ($time_type) {
                case 1:
                    $time_type = 'created_at';
                    break;
                case 2:
                    $time_type = 'updated_at';
                    break;
                case 3:
                    $time_type = 'deleted_at';
                    break;
            }
            $query = $query->whereBetween($time_type, [$start_time, $end_time]);
        }
        $query->with('skus')->latest();
        return parent::index();
    }

    /**
     * 热销产品详情
     * @param $spu
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    public function getGoodsDetail()
    {
        $spu = $this->formData['spu'] ?? '';
        return $this->model::query()->with(['skus', 'category', 'logistics'])->where('spu', $spu)->firstOrFail();
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
