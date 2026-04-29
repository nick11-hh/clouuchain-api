<?php

namespace App\Services\Admin;

use App\Helper\CurrencyConverter;
use App\Http\Resources\Client\ExpressLinePriceRegionList;
use App\Http\Resources\Client\ExpressLineRegionInfo;
use App\Lib\Code;
use App\Models\Admin;
use App\Models\ExpressLineModel;
use App\Models\ExpressLineRegion;
use App\Models\ExpressLineRegionPostcodeArea;
use App\Models\ExpressLineRegionPostcodeAreaModel;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageProp;
use App\Models\SelfPickupStation;
use App\Models\User;
use App\Models\WarehouseAddress;
use App\Rules\CanadianPostalCodeRange;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use App\Exceptions\AccidentException;

class ExpressPriceService extends BaseService
{
    protected bool $isStation = false;

    protected bool $withPostArea = false;

    /**
     * @param array $request
     * @param bool $isReturn
     * @return JsonResponse|array
     * @throws Exception
     */
    public function query(array $request, bool $isReturn = true): JsonResponse|array
    {
        //$this->sqlLog();

        app('log')->info('当前传入的参数为:', $request ?? []);

        $payload = validator($request, [
            'address_ids' => 'nullable',
            'prop_ids' => ['nullable'],
            'package_ids' => ['nullable'],
            'is_great_value' => ['nullable'],
            'is_verify_area' => ['nullable'],// 是否校验地区
//            'warehouse_id' => ['nullable'],
            'country_id' => ['nullable'],
            'area_id' => 'sometimes|nullable',
            'sub_area_id' => 'sometimes|nullable',
            'length' => ['nullable', 'numeric', 'sometimes', 'nullable'],
            'width' => ['required_with:length', 'numeric', 'sometimes', 'nullable'],
            'height' => ['required_with:length', 'numeric', 'sometimes', 'nullable'],
            'weight' => ['required_with:length', 'numeric', 'sometimes', 'nullable'],
            'type' => 'sometimes|nullable',
            'is_group' => 'sometimes|nullable',
            'ignore_min_weight' => 'sometimes|nullable',
            'ignore_weight' => 'sometimes|nullable',
            'icon_filter' => 'sometimes|nullable',
            'is_delivery' => 'sometimes|nullable',
            'floor' => 'sometimes|nullable|integer|gt:0',
            'station_id' => 'sometimes|nullable',
            'postcode' => 'sometimes|nullable',
            'group_id' => 'sometimes|nullable',
            'only_for_stg' => 'sometimes|nullable|int:0,1',
            'group_type' => 'sometimes|nullable|int:1,2',
            'with_post_area' => 'sometimes|nullable|int:0,1',
        ])->validate();

        if(!empty($payload['station_id']) && empty($payload['postcode'])) {
            $station = SelfPickupStation::query()->findOrFail($payload['station_id']);
            if($station->postcode ?? null) {
                $payload['postcode'] = $station->postcode;
            }
        }

        $expressLines = ExpressLineModel::with('icon:id,icon,name')
            ->with('group')
            ->with('props:id,name,color,font_color')
//            ->with('labels:id,name')
            ->with('regions:id,express_line_id,reference_time')
            ->with('regions.areas')
            ->with('regions.postcodeAreas')
            ->with('regions.prices')
            ->with('prices:id,express_line_id,type,start,end,price,unit_weight,first_weight')
            ->with('priceRules:id,express_line_id,type,start,end,unit_weight')
            ->with('defaultStation:id,name,country_id,address,contactor,contact_info')
            ->with('selfPickupStations:id,name,country_id,address,contactor,contact_info,limit_one_weight,limit_many_weight,limit_length,is_stg')
            ->with('defaultStation.country')
//            ->with('warehouses:id,warehouse_name,receiver_name,timezone,phone,postcode,address,tips,enabled,is_stg')
            ->with('authUserGroups:id')
            ->with('authMemberLevels:id')
            ->with('authUserTags:id')
            ->with('authUsers:id')
            ->with('groupConfig:id,express_line_id,is_group')
            ->when(($payload['type'] ?? null) == 2, function (Builder $query) use ($payload) {
                $query->whereHas('selfPickupStations', function ($query) use ($payload) {
                    $query->when($payload['area_id'] ?? null, function ($query) use ($payload) {
                        $query->where('area_id', $payload['area_id']);
                    })->when($payload['sub_area_id'] ?? null, function ($query) use ($payload) {
                        $query->where('sub_area_id', $payload['sub_area_id']);
                    });
                });
            })
            ->when($payload['station_id'] ?? null, function (Builder $query) use ($payload) {
                $query->whereHas('selfPickupStations', function ($query) use ($payload) {
                    $query->whereKey($payload['station_id']);
                });
            })
            ->when(($payload['is_group'] ?? null) == 1, function (Builder $query) use ($payload) {
                $query->whereHas('groupConfig', function ($query) use ($payload) {
                    $query->where('is_group', 1);
                    // 限制查询拼团类型，1 只限公开的，2 只限私人的，0 公开和私人都可以
                    if (isset($payload['group_type'])) {
                        $query->whereIn('type', [0, $payload['group_type']]);
                    }

                });
            })
            ->when(isset($payload['only_for_stg']), function (Builder $query) use ($payload) {
                $query->whereHas('group', function ($query) use ($payload) {
                    $query->where('only_for_stg', $payload['only_for_stg']);
                });
            })
            ->when(!($payload['is_great_value'] ?? 0) && ($payload['is_group'] ?? 0) != 1, function (Builder $query) use ($payload) {
                if (empty($payload['package_ids'])) {
                    return;
                }
                //只在下单时过滤，在非超值线路的条件下，过滤只包含拼团渠道的线路
                $query->whereHas('group', function ($query) {
                    $query->where('only_for_group', 0);
                });
            })
            ->when($payload['is_great_value'] ?? null, function (Builder $query) use ($payload) {
                $query->where('is_great_value', $payload['is_great_value']);
            })
            ->when(isset($payload['is_delivery']), function (Builder $query) use ($payload) {
                $query->whereIn('is_delivery', [$payload['is_delivery'], 2]);
            })
            ->when($payload['group_id'] ?? null, function (Builder $query) use ($payload) {
                $query->where('group_id', $payload['group_id']);
            })
            ->whereHas('regions', function ($query) {
                $query->where('enabled', 1);
            })
            ->where('enabled', 1)
            ->where('is_hidden', 0)
            ->get();

        $this->withPostArea = ($payload['with_post_area'] ?? 0) && !($payload['postcode'] ?? null);

        // 通过一组区域确定还是多组地址确定区域
        if ($payload['country_id'] ?? null) {
            if (($payload['is_great_value'] ?? 0)) {
                $expressLines = $this->filterByCountry($expressLines, $payload);
            } else {
                $expressLines = $this->filterByRegion($expressLines, $payload);
            }
        }

//        //如果使用自提点匹配线路 并且没有国家ID 使用自提点的国家ID
//        if (($payload['station_id'] ?? 0) && !($payload['country_id'] ?? null)) {
//            $station = SelfPickupStation::query()->findOrFail($payload['station_id']);
//
//            $payload['country_id'] = $station->country_id;
//            $payload['area_id'] = $station->area_id;
//            $payload['sub_area_id'] = $station->sub_area_id;
//            $this->isStation = 1;
//        }
//
//        if ($expressLines->isEmpty()) {
//            throw new AccidentException('当前区域暂无可用线路!', Code::OPERATE_FAIL);
//        }
//
//        $expressLines = $expressLines->when($payload['warehouse_id'] ?? null, function ($expressLines) use ($payload) {
//            return $expressLines->filter(function ($value) use ($payload) {
//                return $value->warehouses
//                    ->filter(fn($w) => $w->enabled === 1)
//                    ->contains(fn($w) => $payload['warehouse_id'] == $w->id);
//            });
//        });
//
//        if ($payload['warehouse_id'] ?? null) {
//            $warehouseStr = WarehouseAddress::query()->find($payload['warehouse_id'])->warehouse_name ?? '';
//            if ($expressLines->isEmpty()) {
//                throw new AccidentException('当前仓库:' . $warehouseStr . '暂无可用线路!', Code::OPERATE_FAIL);
//            }
//        }

        $expressLines = $expressLines->when($payload['prop_ids'] ?? null, function ($expressLines) use ($payload) {
            if (is_string($payload['prop_ids'])) {
                $payload['prop_ids'] = explode(',', $payload['prop_ids']);
            }

            return $expressLines->filter(function ($value) use ($payload) {
                // 1:必须全部包含 0:只要包含其中一个
                if ($value->prop_mode === 1) {
                    return array_diff($payload['prop_ids'], $value->props->modelKeys()) === []
                        && $value->props->count() === count($payload['prop_ids']);
                } else {
                    return array_diff($payload['prop_ids'], $value->props->modelKeys()) === [];
                }
            });
        });

        if ($payload['prop_ids'] ?? null) {
            $propString = PackageProp::query()->whereKey($payload['prop_ids'])->pluck('name')->join(',');
            if ($expressLines->isEmpty()) {
                throw new AccidentException("Current attribute combination: {$propString} No lines available!", Code::OPERATE_FAIL);
            }
        }

        $res = [];
        $ignoreMinWeight = $payload['ignore_min_weight'] ?? false;
        $ignoreWeight = $payload['ignore_weight'] ?? false;

        if ($payload['length'] ?? null) {
            $payload['weight'] ??= 1018;
            /** @var ExpressLineModel $expressLine */
            foreach ($expressLines as $expressLine) {
                if ($expressLine->base_mode === ExpressLineModel::BASE_MODE_WEIGHT) {
                    $useVol = false;
                    //先判断下免抛条件
                    $noThrowCondition = $expressLine->no_throw_condition;

                    if (!empty($noThrowCondition) && $noThrowCondition['checked'] != 1) {
                        $noThrowCondition = [];
                    }

                    if ((new Order())->isNoThrow(
                        $noThrowCondition,
                        $payload['length'] * 100,
                        $payload['width'] * 100,
                        $payload['height'] * 100,
                        $expressLine,
                        $payload['weight']
                    )) {
                        $countWeight = $payload['weight'];
                    } else {
                        //has_factor 0 1 0没有  1  有.
                        $volumeWeight = 0;
                        $useVol = false;
                        if ($expressLine->factor) {
                            $volumeWeight = (int)(($payload['length']
                                    * $payload['width']
                                    * $payload['height']
                                    * 1000 / $expressLine->factor)
                                * $expressLine->has_factor);
                        }
                        // 半抛计费重量和普通两者取大
                        if ($expressLine->is_avg_weight && $volumeWeight > $payload['weight']) {
                            $countWeight = ceil(($payload['weight'] + $volumeWeight) / 2);
                        } elseif ($payload['weight'] == 1018) {
                            $countWeight = $payload['weight'];
                        } else {
                            $countWeight = max($volumeWeight, $payload['weight']);
                        }
                    }
                    if($expressLine->payment_weight_int){
                        $countWeight = _roundDownTo($countWeight);
                    }
                } else {
                    $useVol = true;

                    if ($expressLine->weight_factor) {
                        $weight = (int)ceil($payload['weight'] / $expressLine->weight_factor * $expressLine->weight_trans);
                    }
                    //其实算出来的体积
                    $countWeight = (int)ceil(($payload['length'] * $payload['width'] * $payload['height']) / 1000);

                    $countWeight = max($countWeight, $weight ?? 0);
                }

                //忽略最小重量时重量上浮为最小重量
                if ($countWeight < $expressLine->min_weight && $expressLine->ceil_weight) {
                    $countWeight = $expressLine->min_weight;
                }
                //计费重量上浮
                if ($expressLine->weight_rise) {
                    $countWeight = _ceilTo($countWeight, $expressLine->weight_rise);
                }
                /*// 忽略重量
                if ($ignoreWeight && $countWeight > $expressLine->max_weight) {
                    $countWeight = $expressLine->max_weight;
                }*/

                if ($this->isWeightMatched($expressLine, $countWeight)) {
                    $region = $expressLine->getRegionByArea(
                        $payload['country_id'] ?? 0,
                        $payload['area_id'] ?? null,
                        $payload['sub_area_id'] ?? null,
                        $payload['postcode'] ?? '',
                        $this->isStation,
                        $this->withPostArea
                    );

                    unset($expressLine->regions, $region->expressLine);

                    $region = $region->load([
                        'prices', 'rules.conditions.userAddressTags', 'servicePrices', 'servicePrices.service',
                        'areas', 'postcodeAreas', 'rules.conditions.remoteTypes',
                    ]);

                    $expressLine->region = ExpressLineRegionInfo::make($region);

                    $expressLine->count_weight = $countWeight;

                    [$expressLine->count_first, $expressLine->count_next] = $fee = $expressLine
                        ->getExpressFeeNew($region, $countWeight, true, true, useVolume: $useVol);
                    $expressLine->expire_fee = array_sum($fee);

                    unset($expressLine->priceGrade, $expressLine->priceRules, $expressLine->preics);

                    $res[] = $expressLine->toArray();
                }
            }
        } elseif ($payload['package_ids'] ?? null) {
            $packages = Package::whereIn('id', $payload['package_ids'])->get();
            /** @var ExpressLineModel $expressLine */
            foreach ($expressLines as $expressLine) {
                if ($expressLine->base_mode === ExpressLineModel::BASE_MODE_WEIGHT) {
                    $useVol = false;
                    $countWeight = $this->getExpectedWeight($expressLine, $packages) * 1000;
                    info('包裹合计重量为:' . $countWeight);
                    if($expressLine->payment_weight_int){
                        $countWeight = _roundDownTo($countWeight);
                        info('包裹合计重量向下取值后为:' . $countWeight);
                    }
                } else {
                    $useVol = true;
                    $countWeight = $this->getExpectedVolume($expressLine, $packages);
                }

                //忽略最小重量时重量上浮为最小重量
                if ($countWeight < $expressLine->min_weight && $expressLine->ceil_weight) {
                    $countWeight = $expressLine->min_weight;
                }
                //计费重量上浮
                if ($expressLine->weight_rise) {
                    $countWeight = _ceilTo($countWeight, $expressLine->weight_rise);
                }

                $expressLine->count_weight = $countWeight ?? 0;

                $region = $expressLine->getRegionByArea(
                    $payload['country_id'] ?? 0,
                    $payload['area_id'] ?? null,
                    $payload['sub_area_id'] ?? null,
                    $payload['postcode'] ?? '',
                    $this->isStation
                );

                unset($expressLine->regions, $region->expressLine);

                $user = $packages->first()->owner;

                $region = $region->load([
                    'prices', 'rules.conditions.userAddressTags', 'servicePrices', 'servicePrices.service',
                    'areas', 'postcodeAreas', 'rules.conditions.remoteTypes',
                ]);

                $fee = [$expressLine->count_first, $expressLine->count_next] = $expressLine
                    ->getExpressFeeNew($region, $countWeight, true, true, user: $user, useVolume: $useVol);

                $expressLine->region = ExpressLineRegionInfo::make($region);
                $expressLine->expire_fee = array_sum($fee);

                unset($expressLine->priceGrade, $expressLine->priceRules, $expressLine->prices);

                if ($countWeight === 0
                    || ($this->isWeightMatched($expressLine, $countWeight)
                        //拼团包裹特殊触发条件
                        || ($ignoreMinWeight && $expressLine->max_weight >= $countWeight)
                        //包裹批量提交订单时忽略重量
                        || $ignoreWeight
                    )
                ) {
                    $res[] = $expressLine->toArray();
                }
            }
        } else {
            /** @var ExpressLineModel $expressLine */
            foreach ($expressLines as $expressLine) {
                //因为这个重量会改变，所以需要重新赋值
                $countWeight = $payload['weight'] ?? 0;
                //忽略最小重量时重量上浮为最小重量
                if ((int)$countWeight > 0 && $countWeight < $expressLine->min_weight && $expressLine->ceil_weight) {
                    $countWeight = $expressLine->min_weight;
                }
                //计费重量上浮
                if ($expressLine->weight_rise) {
                    $countWeight = _ceilTo($countWeight, $expressLine->weight_rise);
                }
                /*// 忽略重量
                if ($ignoreWeight && $countWeight > $expressLine->max_weight) {
                    $countWeight = $expressLine->max_weight;
                }*/

                $expressLine->count_weight = $countWeight;
                // 不能确定是什么分区的时候 或者是 查询推荐线路的时候
                // 默认第一个区域的价格
                if (!isset($payload['country_id']) || ($payload['is_great_value'] ?? 0)) {
                    $region = $expressLine->regions->firstWhere('enabled', 1);
                } else {
                    $region = $expressLine->getRegionByArea(
                        $payload['country_id'] ?? 0,
                        $payload['area_id'] ?? null,
                        $payload['sub_area_id'] ?? null,
                        $payload['postcode'] ?? '',
                        $this->isStation,
                        $this->withPostArea
                    );
                }
                // 如果没有匹配到区域，则跳过
                if (!$region) {
                    continue;
                }

                $regions = $expressLine->regions;
                unset($region->expressLine, $expressLine->regions);

                $expressLine->regions = ExpressLinePriceRegionList::collection($regions);

                $region = $region->load([
                    'prices', 'rules.conditions.userAddressTags', 'servicePrices', 'servicePrices.service',
                    'areas', 'postcodeAreas', 'rules.conditions.remoteTypes',
                ]);

                $expressLine->region = ExpressLineRegionInfo::make($region);

                if (!($payload['only_for_stg'] ?? 0)) {
                    // 没有尺寸数据只按重量计算
                    if ($expressLine->base_mode === ExpressLineModel::BASE_MODE_VOLUME
                        && (!($payload['is_great_value'] ?? 0) || !($payload['is_group'] ?? 0))
                    ) {
                        continue;
                    }
                }

                if ($countWeight) {
                    [$expressLine->count_first, $expressLine->count_next] = $expressLine
                        ->getExpressFeeNew(
                            $region,
                            $countWeight,
                            true,
                            true,
                        );

                    $expressLine->expire_fee = array_sum([$expressLine->count_first, $expressLine->count_next]);
                } else {
                    [$expressLine->count_first, $expressLine->count_next] = [0, 0];
                    $expressLine->expire_fee = 0;
                }

                unset($expressLine->priceGrade, $expressLine->priceRules, $expressLine->prices);

                if ($countWeight === 0
                    || $this->isWeightMatched($expressLine, $countWeight)
                ) {
                    $res[] = $expressLine->toArray();
                }
            }
        }

        if ($res === [] && isset($countWeight)) {
            throw new AccidentException('当前计费重量/体积: ' . ($countWeight / 1000) . 'kg, 暂无可用线路!', Code::OPERATE_FAIL);
        }

        $res = self::userAuth(collect($res), params: $request);


        if ($payload['floor'] ?? 0) {
            $res = $res->map(function ($e) use ($payload) {
                $e['floor_fee'] = $payload['floor'] * 50 * 100;
                $e['expire_fee'] += $e['floor_fee'];

                return $e;
            });
        }

        if ($payload['icon_filter'] ?? null) {
            $res = $res->groupBy('icon_id')->map(function ($items) {
                $need = collect([]);
                $tmp = collect([]);
                foreach ($items as $item) {
                    if ($item['is_unique']) {
                        $tmp->add($item);
                    } else {
                        $need->add($item);
                    }
                }
                //可能是空的
                if ($tmp->isNotEmpty()) {
                    return $need->add($tmp->sortBy(fn($item) => $item['expire_fee'])->first());
                }
                return $need;
            })->values()->flatten(1);
        }

//        if(isset($payload['only_for_stg'])){
//            $res = $this->filterIsStgWarehouseAndSelfStation($res, $payload['only_for_stg']);
//        }

        $currencyConverter = new CurrencyConverter();
        $logisticsCurrency = 'CNY';
        //最后按线路价格升序排列
        $res = $res->map(function ($value) use ($currencyConverter, $logisticsCurrency) {
            $value['remark'] = str_replace('%', '％', $value['remark']);
            $referenceTime = $value['region']?->reference_time ?? '';
            if ($referenceTime) {
                $value['reference_time'] = $referenceTime;
            }

            $value['origin_logistics_fee'] = $value['expire_fee']; //原始币种物流价格
            $value['origin_logistics_currency'] = $logisticsCurrency;
            if ($logisticsCurrency == 'CNY') {
                $value['expire_fee'] = $currencyConverter->reversedCurrenciesExchange($value['expire_fee']);
            }

            return $value;
        })->sortBy(function ($value) {
            // if (in_array(self::getCompanyId(), [10, 242])) {
            //     return $value['labels'] ? 0: 1;
            // }
            return $value['expire_fee'];
        })->values()->all();

        return $res;
    }

    /**
     * 通过国家过滤
     *
     * @param Collection $expressLines
     * @param array $data
     * @return Collection
     */
    protected function filterByCountry(Collection $expressLines, array $data): Collection
    {
        return $expressLines->filter(function ($value) use ($data) {
            $regions = $value->regions->filter(function (ExpressLineRegion $v) {
                // 已经启用 且 包含非零的价格即认为已经设置好了
                // return $v->enabled && $v->prices->filter(fn($p) => $p->price)->count();
                return $v->enabled && $v->prices->filter(fn($p) => $p->price >= 0)->count();

            });
            // 只需要判断这个有包含某个国家的区域就行了
            foreach ($regions as $region) {
                if ($region->areas->contains(function ($v) use ($data) {
                        return $data['country_id'] == $v->country_id;
                    })
                    || ($region->country_id == $data['country_id'] && $region->type === ExpressLineRegion::TYPE_POSTCODE)
                ) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * @param $expressLines
     * @param array $data
     * @return mixed
     * @throws ValidationException
     */
    protected function filterByRegion($expressLines, array $data)
    {
        return $expressLines->filter(function ($value) use ($data) {
            $regions = $value->regions->filter(function (ExpressLineRegion $v) {
                // 已经启用 且 包含非零的价格即认为已经设置好了
                // return $v->enabled && $v->prices->filter(fn($p) => $p->price)->count();
                return $v->enabled && $v->prices->filter(fn($p) => $p->price >= 0)->count();
            });

            foreach ($regions as $region) {
                if ($region->type === ExpressLineRegion::TYPE_AREA) {
                    if ($region->areas->contains(function ($v) use ($data) {
                        return ExpressLineModel::verifyArea($v, $data);
                    })) {
                        return true;
                    }
                } else {
                    if ($region->country_id == $data['country_id']
                        && ($region->postcodeAreas->contains(function ($area) use ($data) {
                                if ($area->type === ExpressLineRegionPostcodeArea::TYPE_RANGE) {

                                    //判断加拿大邮编范围做特殊处理
                                    if ($data['country_id'] == ExpressLineRegionPostcodeAreaModel::CANADA_COUNTRY_ID) {
                                        $validator = Validator::make($data, [
                                            'postcode' => ['required', new CanadianPostalCodeRange($area->start, $area->end)],
                                        ]);

                                        return $validator->passes();
                                    } else {
                                        return postcode_integer($data['postcode'] ?? '') >= postcode_integer($area->start)
                                            && postcode_integer($data['postcode'] ?? '') <= postcode_integer($area->end);
                                    }

                                } elseif ($area->type === ExpressLineRegionPostcodeArea::TYPE_FIXED) {
                                    return ($data['postcode'] ?? '') == $area->start && !$area->end;
                                }

                                return false;
                            })
                            || $this->withPostArea)
                    ) {
                        return true;
                    }
                }
            }

            return false;
        });
    }

    /**
     * @param ExpressLineModel $expressLine
     * @param $countWeight
     * @return bool
     */
    protected function isWeightMatched(ExpressLineModel $expressLine, $countWeight): bool
    {
        //符合重量限制或者符合多箱重量限制
        return (in_array($expressLine->mode, [ExpressLineModel::MODE_1, ExpressLineModel::MODE_GRADE_NEXT, ExpressLineModel::MODE_RANGE_FIRST_NEXT])
                && $expressLine->max_weight >= $countWeight
                && $expressLine->min_weight <= $countWeight
            )
            || (
                in_array($expressLine->mode, [ExpressLineModel::MODE_2, ExpressLineModel::MODE_MIX])
                && ($expressLine->max_weight >= $countWeight && $expressLine->multi_box_min_weight <= $countWeight)
                && $expressLine->multi_boxes
            )
            || (
                in_array($expressLine->mode, [ExpressLineModel::MODE_2, ExpressLineModel::MODE_MIX])
                && $expressLine->max_weight >= $countWeight
                && $expressLine->min_weight <= $countWeight
                && !$expressLine->multi_boxes
            );
    }

    /*
     * @param ExpressLine $expressLine
     * @param $packages
     * @return float
     */
    public function getExpectedWeight(ExpressLineModel $expressLine, $packages): float
    {
        //计算预计重量
        return $packages->reduce(function ($fee, $package) use ($expressLine) {
            $volumeWeight = 0;
            //先判断下免抛条件
            $noThrowCondition = $expressLine->no_throw_condition;

            if (!empty($noThrowCondition) && $noThrowCondition['checked'] != 1) {
                $noThrowCondition = [];
            }

            if ((new Order())->isNoThrow(
                $noThrowCondition,
                $package->length,
                $package->width,
                $package->height,
                $expressLine,
                $package->package_weight
            )) {
                $countWeight = $package->package_weight / 1000;
            } else {
                if ($expressLine->factor) {
                    $volumeWeight = ceil(($package->length / 100
                            * $package->width / 100
                            * $package->height / 100
                            / $expressLine->factor)
                        * 1000
                        * $expressLine->has_factor); // g
                }
                // 半抛计费重量和普通两者取大
                if ($expressLine->is_avg_weight && $package->package_weight < $volumeWeight) {
                    $countWeight = ceil(($package->package_weight / 1000 + ($volumeWeight / 1000)) / 2);
                } else {
                    $countWeight = max($package->package_weight / 1000, $volumeWeight / 1000);
                }
            }

            return $fee + $countWeight;
        }, 0);
    }

    /**
     * @param ExpressLineModel $expressLine
     * @param $packages
     * @return int
     */
    public function getExpectedVolume(ExpressLineModel $expressLine, $packages): int
    {
        //计算预计体积
        return $packages->reduce(function ($sum, $package) use ($expressLine) {
            if ($expressLine->weight_factor) {
                $volume = (int)ceil($package['package_weight'] / $expressLine->weight_factor * $expressLine->weight_trans);
            }
            $realVolume = (int)ceil(($package->length / 100 * $package->width / 100 * $package->height / 100) / 1000);

            $countVolume = max($realVolume, $volume ?? 0);

            return $sum + $countVolume;
        }, 0);
    }

    public static function userAuth(Collection $res, $isThrow = true, $params = []): Collection
    {
        //兼容用户未登录时查询渠道路线价格
        $user = null;
        if (auth()->check()) {
            $user = auth()->user();

            if (!$user) {
                $user = auth('api')->user();
            }
        }

        if ($user instanceof Admin && !isset($params['customer_id'])) {
            return $res;
        }

        if (!$user) {
            $res = $res->filter(fn($expressLine) => $expressLine['auth_target'] == ExpressLineModel::AUTH_ALL);
        } else {
            // $userTagIds = $user->tags()->get()->modelKeys();

            $userTagIds = [];
            if($user instanceof User) {
                $userTagIds = $user->tags()->get()->modelKeys();
            }

            if(isset($params['customer_id']) && !empty($params['customer_id'])) {
                $user->id = $params['customer_id'];
            }

            if(isset($params['customer_group']) && !empty($params['customer_group'])) {
                $user->user_group_id = $params['customer_group'];
            }

            $res = $res->filter(function ($expressLine) use ($user,$userTagIds) {
                $epl = is_array($expressLine) ? $expressLine : $expressLine->toArray();
                return ($expressLine['auth_target'] == ExpressLineModel::AUTH_ALL)
                    || in_array($user->user_group_id, array_column($epl['auth_user_groups'] ?? [], 'id'))
                    || in_array($user->member?->level_id, array_column($epl['auth_member_levels'] ?? [], 'id'))
                    || array_intersect($userTagIds, array_column($epl['auth_user_tags'] ?? [], 'id'))
                    || in_array($user->getRawOriginal('id'), array_column($epl['auth_users'] ?? [], 'id'));
            });
        }
        // else {
        //     $res = collect([]);
        // }

        if ($isThrow && $res->isEmpty()) {
            throw new AccidentException('匹配的线路只针对部分客户开放!', Code::OPERATE_FAIL);
        }

        return $res;
    }

    protected function filterIsStgWarehouseAndSelfStation(Collection $res, $isStg): Collection
    {
        return $res->map(function ($expressLine) use ($isStg){

            /**@var ExpressLineModel $expressLine*/
            if(!empty($expressLine['self_pickup_stations'])){
                $expressLine['self_pickup_stations'] = collect($expressLine['self_pickup_stations'])->filter(function($sps) use ($isStg){
                    return $sps['is_stg'] == $isStg;
                })->values()->all();
            }

            if(!empty($expressLine['warehouses'])){
                $expressLine['warehouses'] = collect($expressLine['warehouses'])->filter(function($w) use ($isStg){
                    return $w['is_stg'] == $isStg;
                })->values()->all();
            }

            return $expressLine;
        });
    }
}

