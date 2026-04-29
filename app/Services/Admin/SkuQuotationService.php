<?php

namespace App\Services\Admin;


use App\Lib\Code;
use App\Models\Country;
use App\Models\Custom;
use App\Models\Goods;
use App\Models\GoodsSku;
use App\Models\SkuQuotationGroupAttrModel;
use App\Models\SkuQuotationGroupModel;
use App\Services\ApiResponseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Exceptions\AccidentException;

class SkuQuotationService extends BaseService
{

    public function __construct(SkuQuotationGroupModel $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function getQuotationList($params)
    {
        $custom = $params['custom'] ?? '';
        $customId = $params['custom_id'] ?? '';
        $sku = $params['sku'] ?? '';
        $spu = $params['spu'] ?? '';

        $query = $this->model::query();
        $query->with('goodsSku:sku_id,id,images,spec_name,weight');
        $query->with('custom:custom_name,id');
        $query->with('goods:goods_name,id,spu');
        $query->with('country:cn_name,id');
        $query->with(['skuQuotationGroupAttr' => function($query) {
            $query->where('is_new', 1);
        }]);

        //客户id为0时则为公共报价，非一客一价
        $query->whereNot('custom_id', 0);

        $query->whereHas('goods', function ($skuQuery) use ($sku) {
            $skuQuery->whereNull('deleted_at');
        });

        $query->when($sku, function ($query) use ($sku) {
            $query->whereHas('goodsSku', function ($skuQuery) use ($sku) {
                if (str_contains($sku, ',')) {
                    $skus = explode(',', $sku);
                    $skuQuery->whereIn('sku_id', $skus);
                } else {
                    $skuQuery->where('sku_id', 'like', "%$sku%");
                }
            });
        });
        $query->when($spu, function ($query) use ($spu) {
            $query->whereHas('goods', function ($skuQuery) use ($spu) {
                $skuQuery->where('spu', 'like', "%$spu%");
            });
        });
        $query->when($custom, function ($query) use ($custom) {
            $query->whereHas('custom', function ($customQuery) use ($custom) {
                $customQuery->where('custom_name', 'like', "%$custom%");
            });
        });
        $query->when($customId, function ($query) use ($customId) {
            $query->where('custom_id', $customId);
        });

        return $query->latest('id')->paginate($params['size'] ?? 10);
    }

    public function getNewQuotation($params)
    {
        $id = $params['id'] ?? '';

        $query = $this->model::query();
        $query->with('goodsSku:sku_id,id,images,spec_name,weight,purchase_price');
        $query->with('custom:custom_name,id');
        $query->with('goods:goods_name,id,spu');
        $query->with('country:cn_name,id');
        $query->with(['skuQuotationGroupAttr' => function($query) {
            $query->where('is_new', 1);
        }]);

        return $query->findOrFail($id);
    }

    public function getHistoryQuotation($params)
    {
        $id = $params['id'] ?? '';

        $query = $this->model::query();
        $query->with('goodsSku:sku_id,id,images,spec_name,weight');
        $query->with('custom:custom_name,id');
        $query->with('goods:goods_name,id,spu');
        $query->with('country:cn_name,id');
        $query->with('skuQuotationGroupAttrHistory', function ($query) {
            $query->orderBy('quantity', 'asc')->orderBy('id', 'desc');
        });

        $data = $query->findOrFail($id);

        $data = $data->toArray();

        //历史标价信息 历史报价跟最新报价分开
        $newData = $newAttr = $oldAttr = [];
        foreach ($data['sku_quotation_group_attr_history'] as $attr) {
            if ($attr['is_new']) {
                $newAttr[] = $attr;
            } else {
                //根据创建时间分组
                $oldAttr[$attr['created_at']][] = $attr;
            }
        }

        //最新的报价
        $data['sku_quotation_group_attr'] = $newAttr;
        $newData[] = $data;

        //历史报价
        if ($oldAttr) {
            foreach ($oldAttr as $old) {
                $data['sku_quotation_group_attr'] = $old;
                $newData[] = $data;
            }
        }
        return $newData;
    }

    /**
     * 获取自定义报价 针对所有客户
     */
    public function getCustomQuotation()
    {
        validator($this->formData, [
            'id' => 'required|int',
            'type' => ['required', Rule::in(['sku', 'spu'])]
        ])->validate();

        //根据sku查询报价信息
        $query = GoodsSku::query();
        $query->with('goods:goods_name,id,spu');

        $query->with(['skuQuotationGroup' => function($query) {
            //查询没有关联客户的报价信息
            $query->where('custom_id', 0);

            //只查询最新的报价
            $query->with(['skuQuotationGroupAttr' => function ($query) {
                $query->where('is_new', 1);
            }]);
        }]);

        if ($this->formData['type'] === 'sku') {
            $query->where('id', $this->formData['id']);
        } else {
            $query->where('goods_id', $this->formData['id']);
        }

        return $query->get();
    }

    public function saveSkuQuotation(array $params)
    {
        $customId = $params['custom_id'] ?? 0;
        DB::beginTransaction();
        try {
            $data = $this->checkSkuQuotationAndFormat($params);

            foreach ($data as $skuId => $skuData) {
                foreach ($skuData['countryData'] as $countryId => $countryData) {
                    //判断是否存在报价
                    $groupData = $this->model::query()->where(['country_id' => $countryId, 'custom_id' => $customId, 'sku_id' => $skuId])->first();

                    //更新报价分组
                    if ($groupData) {
                        $parentId = $groupData->id ?? 0;

                        SkuQuotationGroupAttrModel::query()->where('parent_id', $parentId)->update(['is_new' => 0]);

                        //更新报价时间
                        $groupData->updated_at = now();
                        $groupData->save();
                    } else {
                        $insertData = $this->model::init($skuId, $customId, $countryId);
                        $insertData['goods_id'] = $skuData['goodsId'];
                        $saveData = $this->query->create($insertData);
                        $parentId = $saveData['id'];
                    }
                    ksort($countryData);
                    foreach ($countryData as $quantity => $price) {
                        $this->saveData($parentId, $quantity, $price);
                    }
                }
            }
            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    /**
     * @desc 自定义报价
     */
    public function saveCustomQuotation(array $params)
    {
        $customId = 0;//自定义报价时客户ID都设置为0
        $type = $params['type'] ?? '';
        DB::beginTransaction();
        try {
            $data = $this->checkSkuQuotationAndFormat($params);

            //spu维度的报价
            if ($type === 'spu') {
                //查询所spu下所有sku的报价信息 并删除sku的报价
                $goodsId = current($data)['goodsId'] ?? 0;
                $skuIds = array_keys($data);

                $group = $this->model::query()->select('id')->whereIn('sku_id', $skuIds)->where(['custom_id' => $customId, 'goods_id' =>$goodsId])->get();

                if ($group->isNotEmpty()) {
                    $this->deleteQuotation($group->toArray());
                }
            }

            foreach ($data as $skuId => $skuData) {
                foreach ($skuData['countryData'] as $countryId => $countryData) {
                    $groupData = [];
                    if ($type === 'sku') {
                        //判断是否存在报价
                        $groupData = $this->model::query()->where(['country_id' => $countryId, 'custom_id' => $customId, 'sku_id' => $skuId])->first();
                    }

                    //更新报价分组
                    if ($groupData) {
                        $parentId = $groupData->id ?? 0;

                        SkuQuotationGroupAttrModel::query()->where('parent_id', $parentId)->update(['is_new' => 0]);

                        //更新报价时间
                        $groupData->updated_at = now();
                        $groupData->save();
                    } else {
                        $insertData = $this->model::init($skuId, $customId, $countryId);
                        $insertData['goods_id'] = $skuData['goodsId'];
                        $saveData = $this->query->create($insertData);
                        $parentId = $saveData['id'];
                    }
                    ksort($countryData);
                    foreach ($countryData as $quantity => $price) {
                        $this->saveData($parentId, $quantity, $price);
                    }
                }
            }
            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    public function deleteQuotation($params)
    {
        $ids = array_column($params, 'id');
        DB::beginTransaction();
        try {
            if (!$this->model::query()->whereIn('id', $ids)->get()->toArray()) {
                throw new AccidentException('商品信息不存在', Code::OPERATE_FAIL);
            }
            $this->model::query()->whereIn('id', $ids)
                ->update(['deleted_at' => date('Y-m-d H:i:s')]);
            SkuQuotationGroupAttrModel::query()->whereIn('parent_id', $ids)
                ->update(['is_new' => 0, 'deleted_at' => date('Y-m-d H:i:s')]);

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    public function saveData($parentId, $quantity, $price)
    {
        $insertData = [
            'parent_id' => $parentId,
            'quantity' => $quantity,
            'price' => $price,
        ];
        SkuQuotationGroupAttrModel::query()->create($insertData);
    }

    /**
     * @desc sku报价数据检测及格式化
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function checkSkuQuotationAndFormat(array $params): array
    {
        $data = [];
        $customId = $params['custom_id'] ?? 0;
        $isCustom = $params['is_custom'] ?? false;//true：自定义报价 false:一客一价
        if ($isCustom === false && !Custom::query()->find($customId)) {
            throw new AccidentException('客户信息不存在', Code::OPERATE_FAIL);
        }

        foreach ($params['skuQuotationData'] as $value) {
            $skuIds = array_column($value['skuList'], 'id');
            $goodsData = GoodsSku::query()->whereIn('id', $skuIds)->get()->toArray();
            if (count($skuIds) !== count($goodsData)) {
                throw new AccidentException('商品信息不存在', Code::OPERATE_FAIL);
            }

            foreach ($value['skuQuotation'] as $v) {
                if (!is_numeric($v['countryId'])) {
                    throw new AccidentException('国家ID不存在', Code::OPERATE_FAIL);
                }

                $countryId = (int)$v['countryId'];//0 所有国家
                if ($countryId !== 0 && !Country::query()->find($countryId)) {
                    throw new AccidentException('国家信息不存在', Code::OPERATE_FAIL);
                }

                foreach ($value['skuList'] as $skuData) {
                    foreach ($v['quotation'] as $quotationData) {
                        if (!$quotationData['quantity'] || !$quotationData['price']) {
                            throw new AccidentException('缺少报价数量或报价金额', Code::OPERATE_FAIL);
                        }
                        //每个sku报价只取第一次报价信息
                        if (!isset($data[$skuData['id']]['countryData'][$v['countryId']][$quotationData['quantity']])) {
                            $data[$skuData['id']]['countryData'][$v['countryId']][$quotationData['quantity']] = $quotationData['price'];
                            $data[$skuData['id']]['goodsId'] = $skuData['goods_id'];
                        }
                    }
                }
            }

        }
        return $data;
    }

}
