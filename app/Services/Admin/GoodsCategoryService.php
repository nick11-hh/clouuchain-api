<?php

namespace App\Services\Admin;


use App\Exceptions\AccidentException;
use App\Lib\Code;
use App\Lib\Language;
use App\Models\Goods;
use App\Models\GoodsCategory;

class GoodsCategoryService extends BaseService
{
    public $filterRules = [
        'name'     => ['=', 'name'],
        'status'   => ['=', 'status'],
        'parent_id'   => ['=', 'parent_id'],
        'operator'   => ['=', 'operator_id'],
        'created_at'   => ['between', ['start_date', 'end_date']],
    ];


    public function __construct()
    {
        $this->model = new GoodsCategory();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with('parent');
        $this->query->latest();
        return parent::index();
    }

    /** 获取产品分类树
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function tree()
    {
        $name = $this->formData['name'] ?? '';
        $status = $this->formData['status'] ?? '';
        $language = $this->formData['language'] ?? Language::ENGLISH;

        $query = $this->model::query()->with(['categories'=> function($query) {
            $query->orderBy('created_at', 'desc');
        }])->with('admin');
        $query->where('parent_id', 0);
        $query->when($name, function ($query) use ($name) {
            return $query->where('name', $name);
        });
        $query->when($status, function ($query) use ($status) {
           return $query->where('status', $status);
        });


        //设置语言
        app()->setLocale($language);

        return $query->get();
    }

    public function show($id)
    {
        return $this->model::query()->findOrFail($id);
    }

    /**
     * @throws \Throwable
     */
    public function store($params)
    {
        validator($params, $this->rules())->validate();
        $exist = $this->model::query()
            ->where('name', $params['name'])
            ->where('parent_id', $params['parent_id'] ?? 0)
            ->first();
        throw_if(!empty($exist), new AccidentException('已存在相同名称的分类', Code::OPERATE_FAIL));

        $goodsCategory = GoodsCategory::init($params);
        return $this->model::query()->create($goodsCategory);
    }

    public function update($id, $params)
    {
        validator($params, $this->rules())->validate();
        $goodsCategory = $this->model::query()->findOrFail($id);
        if (isset($params['name'])) {
            $goodsCategory->name = $params['name'];
            $goodsCategory->setTranslation('name_translate', Language::ENGLISH, $params['name']);
        }

        if (isset($params['name_cn'])) {
            $goodsCategory->setTranslation('name_translate', Language::CHINESE, $params['name_cn']);
        }

        if (isset($params['name_ru'])) {
            $goodsCategory->setTranslation('name_translate', Language::RUSSIAN, $params['name_ru']);
        }

        if (isset($params['name_ar'])) {
            $goodsCategory->setTranslation('name_translate', Language::ARABIC, $params['name_ar']);
        }

        if (isset($params['name_pt'])) {
            $goodsCategory->setTranslation('name_translate', Language::PORTUGAL, $params['name_pt']);
        }

        if (isset($params['name_vi'])) {
            $goodsCategory->setTranslation('name_translate', Language::VIETNAM, $params['name_vi']);
        }

        if (isset($params['description'])) {
            $goodsCategory->description = $params['description'];
        }

        if (isset($params['parent_id'])) {
            $goodsCategory->parent_id = $params['parent_id'];
        }

        if (isset($params['is_recommend'])) {
            $goodsCategory->recommended_time = $params['is_recommend'] > 0 ? now() : null;
        }

        if (isset($params['image'])) {
            $goodsCategory->image = $params['image'];
        }

        return $goodsCategory->save();
    }

    public function updateStatus($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'status' => 'required|int'
        ])->validate();
        $data = ['status' => $params['status']];
        return $this->model->whereIn('id', $params['ids'])->update($data);
    }

    public function deletes($params)
    {
        validator($params, [
            'ids' => 'required|array',
        ])->validate();

        $ids = $params['ids'];

        throw_if(Goods::query()->whereIn('category_id', $ids)->exists(),
                 new AccidentException('分类下存在商品，请移除分类下的商品后再删除',
                 Code::OPERATE_FAIL),
        );

        return $this->model::query()->whereIn('id', $ids)->delete();
    }

    public function rules()
    {
        return [
            'name' => 'required|string',
            'parent_id' => 'sometimes|nullable|int',
            'description' => 'sometimes|nullable|string',
            'image' => 'sometimes|nullable|string',
        ];
    }

}
