<?php
namespace App\Services\Open;

use App\Models\Goods;
use App\Models\GoodsSku;
use App\Models\GoodsCategory;
use App\Models\SystemConfig;
use App\Helper\CurrencyConverter;
use App\Services\Base\SystemConfigService;

class Goods1688Service extends BaseService
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
        $query = $this->query->where('status_1688', Goods::STATUS_ENABLE)->where('origin_type', 1);
        if ($category_id) {
            $query = $query->where('category_id', '=', $category_id);
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
}
