<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:40
 */

namespace App\Services\Admin;

use App\Lib\Code;
use App\Lib\Language;
use App\Models\ExpressLineGroupsModel;
use App\Models\ExpressLineLabelsModel;
use App\Models\ExpressLineModel;
use App\Models\ExpressLinePrice;
use App\Models\ExpressLinePricesModel;
use App\Models\ExpressLineRegion;
use App\Models\PackageProp;
use App\Models\WarehouseAddress;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;
use App\Exceptions\AccidentException;

class ExpressLineConfigService extends BaseService
{
    public function __construct(ExpressLineModel $expressLine)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = $expressLine;
        $this->query = $expressLine->newQuery();
        $this->setFilterRules();
    }

    /**
     * @param array $data
     * @return mixed
     * @throws ValidationException|Throwable
     */
//    public function createBasicConfig(array $data)
//    {
//        validator($data, $this->basicRules(true))->validate();
//
//        throw_unless(PackageProp::isValid($data['prop_ids']), new AccidentException('包裹属性错误！', Code::OPERATE_FAIL));
//
//        !empty($data['label_ids']) && throw_unless(ExpressLineLabelsModel::isValid($data['label_ids']), new AccidentException('标签错误！', Code::OPERATE_FAIL));
//
//        if(!empty($data['code'])){
//            throw_if(
//                ExpressLineModel::query()->where('code', $data['code'])->first(),
//                new AccidentException('当前编码已存在,请换一个', Code::OPERATE_FAIL)
//            );
//        }
//
//        throw_unless(WarehouseAddress::isValid($data['warehouse_ids']), new AccidentException('仓库地址错误', Code::OPERATE_FAIL));
//
//        return DB::transaction(function () use ($data) {
//            /** @var ExpressLineModel $epl */
//            $epl = ExpressLineModel::query()->create([
//                'cn_name' => $data['name'],
//                'is_great_value' => $data['is_great_value'],
//                'icon_id' => $data['icon'] ?? 0,
//                'remark' => $data['remark'],
//                'need_clearance_code' => $data['need_clearance_code'] ?? 0,
//                'need_personal_code' => $data['need_personal_code'] ?? 0,
//                'need_id_card' => $data['need_id_card'] ?? 0,
//                'is_unique' => $data['is_unique'] ?? 0,
//                'is_delivery' => $data['is_delivery'] ?? 0,
//                'default_pickup_station_id' => $data['default_pickup_station_id'] ?? null,
//                'min_weight' => 0,
//                'max_weight' => 999 * 1000,
//                'group_id' => $data['group_id'],
//                'order_mode' => $data['order_mode'] ?? 0,
//                'require_size' => $data['require_size'] ?? 0,
//                'tips' => $data['tips'] ?? '',
//                'code' => $data['code'] ?? '',
//                'prop_mode' => $data['prop_mode'] ?? 0,
//                'payment_weight_int' => $data['payment_weight_int'] ?? 0,
//                'enabled' => 0,//默认不开启
//            ]);
//
//            $epl->setTranslation('name', Language::CHINESE, $data['name']);
//            $epl->setTranslation('name', Language::ENGLISH, $data['en_name']);
//            $epl->setTranslation('name', Language::RUSSIAN, $data['ru_name']);
//            $epl->setTranslation('name', Language::ARABIC, $data['ar_name']);
//            $epl->setTranslation('name', Language::PORTUGAL, $data['pt_name']);
//            $epl->setTranslation('name', Language::VIETNAM, $data['vi_name']);
//            $epl->setTranslation('en_name', Language::CHINESE, $data['name']);
//            $epl->setTranslation('en_name', Language::ENGLISH, $data['en_name']);
//            $epl->save();
//
//            $epl->props()->sync(collect($data['prop_ids'])->unique());
//            !empty($data['label_ids']) && $epl->labels()->sync(collect($data['label_ids'])->unique());
//            $epl->warehouses()->sync(collect($data['warehouse_ids'])->unique());
//
//            return $epl;
//        });
//    }

    /**
     * @param array $data
     * @return mixed
     * @throws ValidationException|Throwable
     */
    public function createBasicConfig(array $data)
    {
        validator($data, $this->basicRules())->validate();

        throw_unless(PackageProp::isValid($data['prop_ids']), new Exception('包裹属性错误！', Code::OPERATE_FAIL));

//        throw_unless(WarehouseAddress::isValid($data['warehouse_ids']), new Exception('仓库地址错误', Code::OPERATE_FAIL));

        return DB::transaction(function () use ($data) {
            /** @var ExpressLineModel $epl */
            $id = ExpressLineModel::query()->where(['channel_code' => $data['channel_code'],'myLogisticsId' => $data['myLogisticsId']])->value('id');
            if ($id) {
                throw new AccidentException($data['express_company_name'] . '物流商下的此物流渠道已经存在，请勿重复添加。', Code::OPERATE_FAIL);
            }
            $newData = [
                'myLogisticsId' => $data['myLogisticsId'],
                'myLogisticsChannelId' => $data['myLogisticsChannelId'],
                'express_company_id' => $data['express_company_id'],
                'express_company_name' => $data['express_company_name'],
                'channel_code' => $data['channel_code'],
                'cn_name' => $data['name'],
                'max_weight' => 999 * 1000,
                'code' => $data['code'] ?? ExpressLineModel::generateCode(),
                'prop_mode' => 0,
                'base_mode' => $data['base_mode'] ?? 0,
                'mode' => $data['mode'] ?? 1,
                'range' => 0,
                'min_weight' => $data['min_weight'] * 1000,
                'ceil_weight' => $data['ceil_weight'] ?? 0,
                'weight_rise' => $data['weight_rise'] ?? 0,
                'multi_boxes_ceil' => 0,
                'payment_weight_int' => 0,
                'enabled' => 1,//默认不开启
                'multi_boxes' => 0,
                'multi_box_min_weight' => 0,
                'is_avg_weight' => 0,
                'has_factor' => 1,
                'factor' => 600,
                'no_throw_condition' => null,
                'weight_factor' => 0,
                'weight_trans' => 0,
                'overweight_status' => 0,
                'overweight_weight' => 0,
                'overweight_remark' => '',
            ];
            $epl = ExpressLineModel::query()->create($newData);

            $epl->setTranslation('name', Language::CHINESE, $data['name']);
//            $epl->setTranslation('name', Language::ENGLISH, $data['en_name']);
//            $epl->setTranslation('en_name', Language::CHINESE, $data['en_name']);
//            $epl->setTranslation('en_name', Language::ENGLISH, $data['en_name']);
            $epl->save();

            $epl->props()->sync(collect($data['prop_ids'])->unique());
//            $epl->warehouses()->sync(collect($data['warehouse_ids'])->unique());

            return $epl;
        });
    }

    /**
     * @param $id
     * @param array $data
     * @return bool
     * @throws Exception|Throwable
     */
//    public function updateBasicConfig($id, array $data): bool
//    {
//        validator($data, $this->basicRules())->validate();
//
//        !empty($data['label_ids']) && throw_unless(ExpressLineLabelsModel::isValid($data['label_ids']), new AccidentException('标签错误！', Code::OPERATE_FAIL));
//
//        if(!empty($data['code'])){
//            throw_if(
//                ExpressLineModel::query()->where('id', '<>', $id)->where('code', $data['code'])->first(),
//                new AccidentException('当前编码已存在,请换一个', Code::OPERATE_FAIL)
//            );
//        }
//
//        return DB::transaction(function () use ($id, $data) {
//            /** @var ExpressLineModel $epl */
//            $epl = ExpressLineModel::query()->findOrFail($id);
//
//            //若更换线路组,则判断线路组是否存在
//            if(!empty($data['group_id'])){
//                throw_unless(
//                    ExpressLineGroupsModel::query()->find($data['group_id']),
//                    new AccidentException('所选线路不存在', Code::OPERATE_FAIL)
//                );
//            }
//
//            $epl->setTranslation('name', Language::CHINESE, $data['name']);
//            $epl->setTranslation('name', Language::ENGLISH, $data['en_name']);
//            $epl->setTranslation('name', Language::RUSSIAN, $data['ru_name']);
//            $epl->setTranslation('name', Language::ARABIC, $data['ar_name']);
//            $epl->setTranslation('name', Language::PORTUGAL, $data['pt_name']);
//            $epl->setTranslation('name', Language::VIETNAM, $data['vi_name']);
//            $epl->setTranslation('en_name', Language::CHINESE, $data['name']);
//            $epl->setTranslation('en_name', Language::ENGLISH, $data['en_name']);
//
//            $epl->update([
//                'cn_name' => $data['name'],
//                'is_great_value' => $data['is_great_value'],
//                'icon_id' => $data['icon'] ?? 0,
//                'remark' => $data['remark'],
//                'need_clearance_code' => $data['need_clearance_code'] ?? 0,
//                'need_personal_code' => $data['need_personal_code'] ?? 0,
//                'need_id_card' => $data['need_id_card'] ?? 0,
//                'is_unique' => $data['is_unique'] ?? 0,
//                'is_delivery' => $data['is_delivery'] ?? 0,
//                'default_pickup_station_id' => $data['default_pickup_station_id'] ?? null,
//                'group_id' => !empty($data['group_id']) ? $data['group_id'] : $epl->group_id,
//                'order_mode' => $data['order_mode'] ?? 0,
//                'require_size' => $data['require_size'] ?? 0,
//                'tips' => $data['tips'] ?? '',
//                'code' => $data['code'] ?? '',
//                'prop_mode' => $data['prop_mode'] ?? 0,
//                'payment_weight_int' => $data['payment_weight_int'] ?? 0,
//            ]);
//
//            $epl->props()->sync(collect($data['prop_ids'])->unique());
//            $epl->labels()->sync(collect($data['label_ids'] ?? [])->unique());
//            $epl->warehouses()->sync(collect($data['warehouse_ids'])->unique());
//
//            return true;
//        });
//    }

    /**
     * 更新基础配置
     * @param $id
     * @param array $data
     * @return mixed
     * @throws Exception|Throwable
     */
    public function updateBasicConfig($id, array $data)
    {
        validator($data, $this->basicRules())->validate();

        return DB::transaction(function () use ($id, $data) {
            /** @var ExpressLineModel $epl */
            $epl = ExpressLineModel::query()->findOrFail($id);
            $origin = [$epl->base_mode, $epl->mode];

            $epl->setTranslation('name', Language::CHINESE, $data['name']);
//            $epl->setTranslation('name', Language::ENGLISH, $data['en_name']);
//            $epl->setTranslation('en_name', Language::CHINESE, $data['en_name']);
//            $epl->setTranslation('en_name', Language::ENGLISH, $data['en_name']);

            $updateData = [
                'myLogisticsId' => $data['myLogisticsId'],
                'myLogisticsChannelId' => $data['myLogisticsChannelId'],
                'express_company_id' => $data['express_company_id'],
                'express_company_name' => $data['express_company_name'],
                'channel_code' => $data['channel_code'],
                'cn_name' => $data['name'],
                'en_name' => $data['en_name'],
                'max_weight' => 999 * 1000,
                'code' => $data['code'] ?? '',
                'prop_mode' => $data['prop_mode'] ?? 0,
                'base_mode' => $data['base_mode'] ?? 0,
                'mode' => $data['mode'] ?? 1,
                'range' => $data['range'] ?? 0,
                'min_weight' => $data['min_weight'] * 1000,
                'ceil_weight' => $data['ceil_weight'] ?? 0,
                'weight_rise' => $data['weight_rise'] ?? 0,
                'multi_boxes_ceil' => $data['multi_boxes_ceil'] ?? 0,
                'payment_weight_int' => $data['payment_weight_int'] ?? 0,
                'multi_boxes' => $data['multi_boxes'] ?? 0,
                'multi_box_min_weight' => ($data['multi_box_min_weight'] ?? 0) * 1000,
                'is_avg_weight' => $data['is_avg_weight'] ?? 0,
                'has_factor' => $data['has_factor'],
                'factor' => $data['factor'] ?: 1,
                'no_throw_condition' => $data['no_throw_condition'] ?? null,
                'weight_factor' => $data['weight_factor'] ?? 0,
                'weight_trans' => $data['weight_trans'] ?? 0,
                'overweight_status' => $data['overweight_status'] ?? 0,
                'overweight_weight' => ($data['overweight_weight'] ?? 0) * 1000,
                'overweight_remark' => $data['overweight_remark'] ?? '',
            ];

            $epl->update($updateData);

            $epl->props()->sync(collect($data['prop_ids'])->unique());
//            $epl->warehouses()->sync(collect($data['warehouse_ids'])->unique());

            if ($epl->base_mode !== $origin[0] || $epl->mode!== $origin[1]) {
                //如果直接更新了原来的价格模式
                //那么也相当于新建
                //不过额外的需要清理原来的价格
                $epl->prices()->delete();

                //查询所有分区以及每个分区对应的重量区间，再单独对每个分区的重量区间初始化
                $regions = ExpressLineRegion::query()->where('express_line_id', $epl->id)->get();
                foreach ($regions as $region) {
                    //重置每个分区对应的重量区间
                    $this->resetPriceRules($region, $epl->mode);

                    (new ExpressLinePriceService())->initBase($epl, $region->id);
                }
            }


            return $epl;
        });
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function updateBillingConfig(int $id, array $data): bool
    {
        $data = validator($data, $this->chargingRules())->validate();

        DB::transaction(function () use ($data, $id) {
            /** @var ExpressLineModel $epl */
            $epl = ExpressLineModel::query()->findOrFail($id);

            $origin = [$epl->base_mode, $epl->mode];

            $range = $data['range'] ?? 0;

            //首重续重模式-默认左开右闭
            if ($data['mode'] === ExpressLineModel::MODE_1) {
                $range = 1;
            }

            $epl->update([
                'base_mode' => $data['base_mode'],
                'mode' => $data['mode'],
                'min_weight' => $data['min_weight'] * 1000,
                'ceil_weight' => $data['ceil_weight'] ?? 0,
                'weight_rise' => $data['weight_rise'] ?? 0,
                'multi_boxes_ceil' => $data['multi_boxes_ceil'] ?? 0,
                'multi_boxes' => $data['multi_boxes'] ?? 0,
                'multi_box_min_weight' => ($data['multi_box_min_weight'] ?? 0) * 1000,
                'is_avg_weight' => $data['is_avg_weight'] ?? 0,
                'has_factor' => $data['has_factor'],
                'factor' => $data['factor'] ?: 1,
                'no_throw_condition' => $data['no_throw_condition'] ?? null,
                'range' => $range,
                'weight_factor' => $data['weight_factor'] ?? 0,
                'weight_trans' => $data['weight_trans'] ?? 0,
                'payment_weight_int' => $data['payment_weight_int'] ?? 0,
                'overweight_status' => $data['overweight_status'] ?? 0,
                'overweight_weight' => ($data['overweight_weight'] ?? 0) * 1000,
                'overweight_remark' => $data['overweight_remark'] ?? '',
            ]);

            if ($epl->base_mode !== $origin[0] || $epl->mode!== $origin[1]) {
                //如果直接更新了原来的价格模式
                //那么也相当于新建
                //不过额外的需要清理原来的价格
                $epl->prices()->delete();

                //查询所有分区以及每个分区对应的重量区间，再单独对每个分区的重量区间初始化
                $regions = ExpressLineRegion::query()->where('express_line_id', $epl->id)->get();
                foreach ($regions as $region) {
                    //重置每个分区对应的重量区间
                    $this->resetPriceRules($region, $epl->mode);

                    (new ExpressLinePriceService())->initBase($epl, $region->id);
                }
            }

            return true;
        });

        return true;
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function updateBillingConfig0625(int $id, array $data): bool
    {
        $data = validator($data, $this->chargingRules())->validate();

        DB::transaction(function () use ($data, $id) {
            /** @var ExpressLineModel $epl */
            $epl = ExpressLineModel::query()->findOrFail($id);

            $origin = [$epl->base_mode, $epl->mode];

            $epl->update([
                'base_mode' => $data['base_mode'],
                'mode' => $data['mode'],
                'min_weight' => $data['min_weight'] * 1000,
                'ceil_weight' => $data['ceil_weight'] ?? 0,
                'weight_rise' => $data['weight_rise'] ?? 0,
                'multi_boxes_ceil' => $data['multi_boxes_ceil'] ?? 0,
                'multi_boxes' => $data['multi_boxes'] ?? 0,
                'multi_box_min_weight' => ($data['multi_box_min_weight'] ?? 0) * 1000,
                'is_avg_weight' => $data['is_avg_weight'] ?? 0,
                'has_factor' => $data['has_factor'],
                'factor' => $data['factor'] ?: 1,
                'no_throw_condition' => $data['no_throw_condition'] ?? null,
                'range' => $data['range'] ?? 0,
                'weight_factor' => $data['weight_factor'] ?? 0,
                'weight_trans' => $data['weight_trans'] ?? 0,
                'payment_weight_int' => $data['payment_weight_int'] ?? 0,
                'overweight_status' => $data['overweight_status'] ?? 0,
                'overweight_weight' => ($data['overweight_weight'] ?? 0) * 1000,
                'overweight_remark' => $data['overweight_remark'] ?? '',
            ]);

            $this->validatePriceGrades($data['grades'], $data['mode'], $data['first_weight'] ?? null);

            $newRules = [];
            if ($data['mode'] == ExpressLineModel::MODE_1) {
                $newRules[] = $epl->priceRules()
                    ->updateOrCreate(
                        [
                            'type' => ExpressLinePrice::TYPE_FIRST_WEIGHT,
                            'start' => $data['first_weight'] * 1000,
                            'end' => $data['first_weight'] * 1000,
                        ],
                        [
                            'unit_weight' => null,
                        ]
                    );

                foreach ($data['grades'] as $grade) {
                    $newRules[] = $epl->priceRules()
                        ->updateOrCreate(
                            [
                                'type' => ExpressLinePrice::TYPE_NEXT_WEIGHT,
                                'start' => $grade['start'] * 1000,
                                'end' => $grade['end'] * 1000,
                            ],
                            [
                                'unit_weight' => $grade['unit_weight'] * 1000,
                            ]
                        );
                }
            } elseif ($data['mode'] == ExpressLineModel::MODE_2) {
                foreach ($data['grades'] as $grade) {
                    $newRules[] = $epl->priceRules()
                        ->updateOrCreate(
                            [
                                'type' => ExpressLinePrice::TYPE_GRADE_WEIGHT,
                                'start' => $grade['start'] * 1000,
                                'end' => $grade['end'] * 1000,
                            ],
                            [
                                'unit_weight' => null,
                            ]
                        );
                }
            } elseif ($data['mode'] == ExpressLineModel::MODE_MIX) {
                $newRules[] = $epl->priceRules()
                    ->updateOrCreate(
                        [
                            'type' => ExpressLinePrice::TYPE_UNIT_WEIGHT,
                            'start' => 0,
                            'end' => 0,
                        ],
                        [
                            'unit_weight' => 1000,
                        ]
                    );

                foreach ($data['grades'] as $grade) {
                    $newRules[] = $epl->priceRules()
                        ->updateOrCreate(
                            [
                                'type' => ExpressLinePrice::TYPE_GRADE_WEIGHT_APPEND,
                                'start' => $grade['start'] * 1000,
                                'end' => $grade['end'] * 1000,
                            ],
                            [
                                'unit_weight' => null,
                            ]
                        );
                }
            } elseif ($data['mode'] == ExpressLineModel::MODE_GRADE_NEXT) {
                $newRules[] = $epl->priceRules()
                    ->updateOrCreate(
                        [
                            'type' => ExpressLinePrice::TYPE_FIRST_WEIGHT,
                            'start' => $data['first_weight'] * 1000,
                            'end' => $data['first_weight'] * 1000,
                        ],
                        [
                            'unit_weight' => null,
                        ]
                    );

                foreach ($data['grades'] as $grade) {
                    $newRules[] = $epl->priceRules()
                        ->updateOrCreate(
                            [
                                'type' => ExpressLinePrice::TYPE_GRADE_NEXT_WEIGHT,
                                'unit_weight' => $grade['unit_weight'] * 1000,
                            ],
                            [
                                'start' => 0,
                                'end' => 0,
                                'unit_weight' => $grade['unit_weight'] * 1000,
                            ]
                        );
                }
            } elseif ($data['mode'] == ExpressLineModel::MODE_RANGE_FIRST_NEXT) {
                foreach ($data['grades'] as $grade) {
                    $newRules[] = $epl->priceRules()
                        ->updateOrCreate(
                            [
                                'type' => ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT,
                                'start' => $grade['start'] * 1000,
                                'end' => $grade['end'] * 1000,
                            ],
                            [
                                'first_weight' => $grade['first_weight'] * 1000,
                                'unit_weight' => $grade['unit_weight'] * 1000,
                            ]
                        );
                }
            }
            // 对于最大重量
            // 只有多级续重直接指定
            // 如果没有直接指定 那么取价格阶梯里面的最大重量
            if (! empty($data['max_weight']) && $data['mode'] == ExpressLineModel::MODE_GRADE_NEXT) {
                $epl->update(['max_weight' => $data['max_weight'] * 1000]);
            } else {
                $maxWeight = collect($newRules)
                    ->sortByDesc(fn ($rule) => $rule->end)
                    ->first()
                    ->end;
                $epl->update(['max_weight' => $maxWeight]);
            }

            //删除原来定义的的规则
            $epl->priceRules()->whereKeyNot(collect($newRules)->pluck('id'))->delete();
            $epl->refresh();
            //如果原来没有价格配置
            //是新建的线路
            //那么直接初始化价格表
            if (! $epl->prices()->count()) {
                (new ExpressLinePriceService())->initBase($epl);
            } elseif ($epl->base_mode !== $origin[0] || $epl->mode!== $origin[1]) {
                //如果直接更新了原来的价格模式
                //那么也相当于新建
                //不过额外的需要清理原来的价格
                $epl->prices()->delete();
                (new ExpressLinePriceService())->initBase($epl);
            } else {
                //如果只是更新了部分数据
                //这种情况就比较复杂了 因为需要保留原来的数据
                //这里的保留逻辑是 如果 start + end 没有改变 直接更新
                //如果变化了 那就插入新的 同时删除原来的
                (new ExpressLinePriceService())->updateBase($epl);
            }

            return true;
        });

        return true;
    }

    /**
     * @param $id
     * @return Model|null
     */
    public function getBillingConfig($id)
    {
        /** @var ExpressLineModel $epl */
        return ExpressLineModel::query()
            ->with(['priceRules', 'warehouses', 'props'])
            ->findOrFail($id);
    }

    /**
     * @param array $grades
     * @param int $type
     * @param float|null $firstWeight
     * @throws Exception
     */
    protected function validatePriceGrades(array $grades, int $type, float $firstWeight = null)
    {
        $grades = collect($grades)->sortBy(fn ($value) => $value['start']);
        $range = $grades->map(fn ($value) => [$value['start'], $value['end']])->flatten()->values();

        if ($type !== ExpressLineModel::MODE_GRADE_NEXT) {
            for ($i = 1; $i < $range->count() - 1; $i += 2) {
                if ($range[$i] != $range[$i + 1]) {
                    throw new AccidentException('需要闭合且连续的重量区间', Code::OPERATE_FAIL);
                }
            }
        }

        if ($type === ExpressLineModel::MODE_1) {
            if ($firstWeight != $range[0]) {
                throw new AccidentException('首重和续重区间未闭合', Code::OPERATE_FAIL);
            }
        }

        if (in_array($type, [ExpressLineModel::MODE_1, ExpressLineModel::MODE_GRADE_NEXT])) {
            $grades->each(function ($v) {
                if (! $v['unit_weight'] > 0) {
                    throw new AccidentException('单位续重必须大于 0', Code::OPERATE_FAIL);
                }
            });
        }
    }

    /**
     * @param ExpressLineRegion $region
     * @param int $mode
     * @return bool
     * @throws Exception
     */
    public function resetPriceRules(ExpressLineRegion $region, int $mode): bool
    {
        DB::transaction(function () use ($region, $mode) {
            //获取分区重量区间
            $region->load('priceRules');

            if ($region->priceRules->isEmpty()) {
                return true;
            }

            //排序并过滤单位重量类型的重量区间
            $priceRules = $region->priceRules->filter(fn($v) => $v->type !== ExpressLinePricesModel::TYPE_UNIT_WEIGHT)
                ->sortBy->start->values();

            //获取首重
            $firstWeight = $priceRules->first()->start;

            //根据计费价格模式重新生成重量区间
            $newRules = [];
            if ($mode === ExpressLineModel::MODE_1) {
                $newRules[] = $region->priceRules()
                    ->updateOrCreate(
                        [
                            'express_line_id' => $region->express_line_id,
                            'type' => ExpressLinePrice::TYPE_FIRST_WEIGHT,
                            'start' => $firstWeight,
                            'end' => $firstWeight,
                        ],
                        [
                            'unit_weight' => null,
                        ]
                    );

                foreach ($priceRules as $rule) {
                    $newRules[] = $region->priceRules()
                        ->updateOrCreate(
                            [
                                'express_line_id' => $region->express_line_id,
                                'type' => ExpressLinePrice::TYPE_NEXT_WEIGHT,
                                'start' => $rule['start'],
                                'end' => $rule['end'],
                            ],
                            [
                                'unit_weight' => $rule['unit_weight'],
                            ]
                        );
                }
            } elseif ($mode === ExpressLineModel::MODE_2) {
                foreach ($priceRules as $rule) {
                    $newRules[] = $region->priceRules()
                        ->updateOrCreate(
                            [
                                'express_line_id' => $region->express_line_id,
                                'type' => ExpressLinePrice::TYPE_GRADE_WEIGHT,
                                'start' => $rule['start'],
                                'end' => $rule['end'],
                            ],
                            [
                                'unit_weight' => null,
                            ]
                        );
                }
            } elseif ($mode === ExpressLineModel::MODE_MIX) {
                $newRules[] = $region->priceRules()
                    ->updateOrCreate(
                        [
                            'express_line_id' => $region->express_line_id,
                            'type' => ExpressLinePrice::TYPE_UNIT_WEIGHT,
                            'start' => 0,
                            'end' => 0,
                        ],
                        [
                            'unit_weight' => 1000,
                        ]
                    );

                foreach ($priceRules as $rule) {
                    $newRules[] = $region->priceRules()
                        ->updateOrCreate(
                            [
                                'express_line_id' => $region->express_line_id,
                                'type' => ExpressLinePrice::TYPE_GRADE_WEIGHT_APPEND,
                                'start' => $rule['start'],
                                'end' => $rule['end'],
                            ],
                            [
                                'unit_weight' => null,
                            ]
                        );
                }
            } elseif ($mode === ExpressLineModel::MODE_GRADE_NEXT) {
                $newRules[] = $region->priceRules()
                    ->updateOrCreate(
                        [
                            'express_line_id' => $region->express_line_id,
                            'type' => ExpressLinePrice::TYPE_FIRST_WEIGHT,
                            'start' => $firstWeight,
                            'end' => $firstWeight,
                        ],
                        [
                            'unit_weight' => null,
                        ]
                    );

                foreach ($priceRules as $rule) {
                    $newRules[] = $region->priceRules()
                        ->updateOrCreate(
                            [
                                'express_line_id' => $region->express_line_id,
                                'type' => ExpressLinePrice::TYPE_GRADE_NEXT_WEIGHT,
                                'unit_weight' => $rule['unit_weight'],
                            ],
                            [
                                'start' => 0,
                                'end' => 0,
                                'unit_weight' => $rule['unit_weight'],
                            ]
                        );
                }
            } elseif ($mode === ExpressLineModel::MODE_RANGE_FIRST_NEXT) {
                foreach ($priceRules as $rule) {
                    $newRules[] = $region->priceRules()
                        ->updateOrCreate(
                            [
                                'express_line_id' => $region->express_line_id,
                                'type' => ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT,
                                'start' => $rule['start'],
                                'end' => $rule['end'],
                            ],
                            [
                                'first_weight' => $rule['first_weight'],
                                'unit_weight' => $rule['unit_weight'],
                            ]
                        );
                }
            }

            //删除原来定义的的规则
            $region->priceRules()->whereKeyNot(collect($newRules)->pluck('id'))->delete();
        });

        return true;
    }

    /**
     * @return string[]
     */
//    protected function basicRules(bool $isCreate = false): array
//    {
//        $rules = [
//            'name' => 'required|string|max:50',
//            'en_name' => 'required|string|max:50',
//            'ru_name' => 'sometimes|nullable|max:50',
//            'is_great_value' => 'required|integer|in:0,1',
//            'icon' => 'sometimes|nullable|integer',
//            'remark' => 'required|string|max:5000',
//            'need_clearance_code' => 'required|in:0,1',
//            'need_personal_code' => 'required|in:0,1',
//            'need_id_card' => 'required|in:0,1',
//            'is_unique' => 'required|in:0,1',
//            'is_delivery' => 'required|in:0,1,2',
//            'default_pickup_station_id' => 'sometimes|nullable|integer',
//            'prop_ids' => 'required|array',
//            'label_ids' => 'nullable|array',
//            'warehouse_ids' => 'required|array',
//            'group_id' => 'nullable|int',
//            'order_mode' => 'nullable|int|in:0,1,2',
//            'require_size' => 'nullable|int|in:0,1',
//            'tips' => 'sometimes|nullable|string|max:500',
//            'code' => 'sometimes|nullable|string|max:50',
//            'prop_mode' => 'sometimes|nullable|int|in:0,1',
//            'payment_weight_int' => 'sometimes|nullable|int|in:0,1',
//        ];
//
//        if ($isCreate) {
//            return array_merge($rules, ['group_id' => 'required|integer']);
//        }
//
//        return $rules;
//    }

    /**
     * @return string[]
     */
    protected function basicRules(): array
    {
        return [
            'myLogisticsId' => 'nullable|int|gt:0',//马帮物流商ID
            'myLogisticsChannelId' => 'nullable|int|gt:0',//马帮物流渠道ID
            'express_company_id' => 'nullable|int|gt:0',//物流商ID
            'express_company_name' => 'nullable|string|max:50',//物流商名称
            'channel_code' => 'nullable|string|max:50',//物流渠道编码
            'name' => 'required|string|max:50',//模板名称
//            'en_name' => 'required|string|max:50',//公开名称
            'prop_ids' => 'required|array',//产品属性
//            'warehouse_ids' => 'required|array',// 仓库ID
//            'prop_mode' => 'required|int|in:0,1',//多品匹配
            'base_mode' => 'required|int|in:0,1',//计费模式
            'mode' => 'required|in:1,2,3,4,5',//计费价格模式
            'min_weight' => 'required|numeric|gt:0',//渠道最小重量
//            'range' => 'sometimes|nullable|in:0,1',//开闭区间
            'ceil_weight' => 'sometimes|nullable|in:0,1',//渠道最小重量
            'weight_rise' => 'sometimes|nullable|in:0,0.1,0.2,0.25,0.5,1',//订单单箱打包重量向上取值
//            'multi_boxes_ceil' => 'sometimes|nullable|in:0,0.1,0.2,0.25,0.5,1',//订单多箱打包重量向上取值
//            'payment_weight_int' => 'sometimes|nullable|in:0,1',//计费重100g以下抹零
//            'has_factor' => 'required|in:0,1',
//            'factor' => 'required|numeric|gt:0',
//            'is_avg_weight' => 'sometimes|nullable|in:0,1',
//            'no_throw_condition' => 'sometimes|nullable|array',
//            'no_throw_condition.type' => 'sometimes|nullable|in:1,2,3,4,5',
//            'no_throw_condition.condition' => 'sometimes|nullable|in:<,<=,>,>=',
//            'no_throw_condition.value' => 'sometimes|nullable|int',
//            'no_throw_condition.checked' => 'sometimes|nullable|in:0,1',
//            'weight_trans' => 'sometimes|nullable|in:0,1',
//            'weight_factor' => 'sometimes|nullable|integer',
//            'overweight_status' => 'sometimes|nullable|in:0,1',
//            'overweight_weight' => 'sometimes|nullable|numeric|gte:0',
//            'overweight_remark' => 'sometimes|nullable|string|max:1024',
        ];
    }

    /**
     * @return string[]
     */
    protected function chargingRules(): array
    {
        return [
            'base_mode' => 'required|in:0,1',
            'ceil_weight' => 'sometimes|nullable|in:0,1',
            'weight_rise' => 'sometimes|nullable|in:0,0.1,0.2,0.25,0.5,1',
            'multi_boxes_ceil' => 'sometimes|nullable|in:0,0.1,0.2,0.25,0.5,1',
            'multi_boxes' => 'required|in:0,1,2,3',
            'multi_box_min_weight' => 'sometimes|nullable|gte:0',
            'mode' => 'required|in:1,2,3,4,5',
            // 'first_weight' => 'required_if:mode,1,4',
            'min_weight' => 'required|numeric|gt:0',
            // 'max_weight' => 'sometimes|nullable|numeric|gte:0',
            // 'grades' => 'required|array',
            // 'grades.*.start' => 'required_unless:mode,4',
            // 'grades.*.end' => 'required_unless:mode,4',
            // 'grades.*.unit_weight' => 'sometimes|nullable',
            // 'grades.*.first_weight' => 'required_if:mode,5',
            'has_factor' => 'required|in:0,1',
            'factor' => 'required|numeric|gt:0',
            'is_avg_weight' => 'sometimes|nullable|in:0,1',
            'no_throw_condition' => 'nullable|array',
            'no_throw_condition.type' => 'nullable|in:1,2,3,4,5',
            'no_throw_condition.condition' => 'nullable|in:<,<=,>,>=',
            'no_throw_condition.value' => 'nullable|int',
            'no_throw_condition.checked' => 'nullable|in:0,1',
            'range' => 'sometimes|nullable|in:0,1',
            'weight_trans' => 'sometimes|nullable|in:0,1',
            'weight_factor' => 'sometimes|nullable|integer',
            'payment_weight_int' => 'sometimes|nullable|in:0,1',
            'overweight_status' => 'sometimes|nullable|in:0,1',
            'overweight_weight' => 'sometimes|nullable|numeric|gte:0',
            'overweight_remark' => 'sometimes|nullable|string|max:1024',
        ];
    }
}
