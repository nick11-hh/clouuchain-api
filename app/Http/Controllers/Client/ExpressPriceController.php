<?php

namespace App\Http\Controllers\Client;

use App\Helper\CurrencyConverter;
use App\Http\Resources\Client\ExpressLineInfo;
use App\Http\Resources\Client\ExpressLinePriceRegionList;
use App\Http\Resources\Client\ExpressLineRegionInfo;
use App\Http\Resources\Client\ExpressLineRegionList;
use App\Http\Traits\SqlLog;
use App\Lib\Code;
use App\Models\Admin;
use App\Models\Country;
use App\Models\Custom;
use App\Models\ExpressLineGroupsModel;
use App\Models\ExpressLineModel;
use App\Models\ExpressLineRegion;
use App\Models\ExpressLineRegionPostcodeArea;
use App\Models\ExpressLineRegionPostcodeAreaModel;
use App\Models\ExpressLineRuleCondition;
use App\Models\Order;
use App\Models\Package;
use App\Models\PackageProp;
use App\Models\SelfPickupStation;
use App\Models\SystemConfig;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\WarehouseAddress;
use App\Models\CustomsQuoteConfig;
use App\Rules\CanadianPostalCodeRange;
use App\Services\Admin\ExpressPriceService;
use App\Services\ApiResponseService;
use App\Services\Client\PriceDisCountService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Exceptions\AccidentException;
use App\Services\Base\SystemConfigService;

class ExpressPriceController extends Controller
{
    use ValidatesRequests, SqlLog;

    protected bool $isStation = false;

    protected bool $withPostArea = false;

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function clientQuery(Request $request): JsonResponse
    {
        $data = $request->all();
        $data['icon_filter'] = true;
        $data['only_for_stg'] = 0;

        return $this->query($data);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function stgQuery(Request $request): JsonResponse
    {
        $data = $request->all();
        $data['only_for_stg'] = 1;

        return $this->query($data);
    }

    /**
     * @param Request $request
     * @return array|JsonResponse
     * @throws Exception
     */
    public function adminQuery(Request $request)
    {
        $data = $request->all();

        return $this->query($data);
    }

    /**
     * 客户端
     * @param Request $request
     * @return array|JsonResponse
     * @throws Exception
     */
    public function adminQuery_client(Request $request)
    {
        $data = $request->all();

        return $this->query_client($data);
    }

    /**
     * @param array $request
     * @param bool $isReturn
     * @param bool $isThrown
     * @return JsonResponse|array
     * @throws Exception
     */
    public function query(array $request, bool $isReturn = true, bool $isThrown = true): JsonResponse|array
    {
        // 物流渠道路线、区域等关联较多，避免 128M 默认内存导致 FatalError
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '256M');
        }
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
            })->when(isset($payload['only_for_stg']), function (Builder $query) use ($payload) {
                $query->whereHas('group', function ($query) use ($payload) {
                    $query->where('only_for_stg', $payload['only_for_stg']);
                });
            })->when(!($payload['is_great_value'] ?? 0) && ($payload['is_group'] ?? 0) != 1, function (Builder $query) use ($payload) {
                if (empty($payload['package_ids'])) {
                    return;
                }
                //只在下单时过滤，在非超值线路的条件下，过滤只包含拼团渠道的线路
                $query->whereHas('group', function ($query) {
                    $query->where('only_for_group', 0);
                });
            })->when($payload['is_great_value'] ?? null, function (Builder $query) use ($payload) {
                $query->where('is_great_value', $payload['is_great_value']);
            })->when(isset($payload['is_delivery']), function (Builder $query) use ($payload) {
                $query->whereIn('is_delivery', [$payload['is_delivery'], 2]);
            })->when($payload['group_id'] ?? null, function (Builder $query) use ($payload) {
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

                    //过滤超重的运费模板
                    if ($this->filterOverweight($region, $countWeight)) continue;

                    $expressLine->region = ExpressLineRegionInfo::make($region);

                    $expressLine->count_weight = $countWeight;

                    [$expressLine->count_first, $expressLine->count_next] = $fee = $expressLine
                        ->getExpressFeeNew($region, $countWeight, true, true, useVolume: $useVol);
                    $expressLine->expire_fee = array_sum($fee);

                    unset($expressLine->priceGrade, $expressLine->priceRules, $expressLine->preics);
                    unset($expressLine->group, $expressLine->props, $expressLine->defaultStation, $expressLine->selfPickupStations, $expressLine->groupConfig, $expressLine->icon);

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

                //过滤超重的运费模板
                if ($this->filterOverweight($region, $countWeight)) continue;

                $fee = [$expressLine->count_first, $expressLine->count_next] = $expressLine
                    ->getExpressFeeNew($region, $countWeight, true, true, user: $user, useVolume: $useVol);

                $expressLine->region = ExpressLineRegionInfo::make($region);
                $expressLine->expire_fee = array_sum($fee);

                unset($expressLine->priceGrade, $expressLine->priceRules, $expressLine->prices);
                unset($expressLine->group, $expressLine->props, $expressLine->defaultStation, $expressLine->selfPickupStations, $expressLine->groupConfig, $expressLine->icon);

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
                    $matchedRegions = collect([$expressLine->regions->firstWhere('enabled', 1)]);
                } else {
                    $matchedRegions = $expressLine->getRegionByAreas(
                        $payload['country_id'] ?? 0,
                        $payload['area_id'] ?? null,
                        $payload['sub_area_id'] ?? null,
                        $payload['postcode'] ?? '',
                        $this->isStation,
                        $this->withPostArea
                    );
                }

                // 如果没有匹配到任何区域，则跳过
                if ($matchedRegions->isEmpty()) {
                    continue;
                }

                // 保存原始区域数据用于后续处理
                $allRegions = $expressLine->regions;

                // 遍历所有匹配的区域
                foreach ($matchedRegions as $region) {
                    $lineCopy = clone $expressLine;

                    unset($region->expressLine, $lineCopy->regions);

                    $lineCopy->regions = ExpressLinePriceRegionList::collection($allRegions);

                    $region = $region->load([
                        'prices', 'rules.conditions.userAddressTags', 'servicePrices', 'servicePrices.service',
                        'areas', 'postcodeAreas', 'rules.conditions.remoteTypes',
                    ]);

                    if ($this->filterOverweight($region, $countWeight)) continue;

                    $lineCopy->region = ExpressLineRegionInfo::make($region);

                    if (!($payload['only_for_stg'] ?? 0)) {
                        if ($lineCopy->base_mode === ExpressLineModel::BASE_MODE_VOLUME
                            && (!($payload['is_great_value'] ?? 0) || !($payload['is_group'] ?? 0))
                        ) {
                            continue;
                        }
                    }

                    if ($countWeight) {
                        [$lineCopy->count_first, $lineCopy->count_next] = $lineCopy
                            ->getExpressFeeNew(
                                $region,
                                $countWeight,
                                true,
                                true,
                                useVolume: false
                            );

                        $lineCopy->expire_fee = array_sum([$lineCopy->count_first, $lineCopy->count_next]);
                    } else {
                        [$lineCopy->count_first, $lineCopy->count_next] = [0, 0];
                        $lineCopy->expire_fee = 0;
                    }

                    unset($lineCopy->priceGrade, $lineCopy->priceRules, $lineCopy->prices);
                    unset($lineCopy->group, $lineCopy->props, $lineCopy->defaultStation, $lineCopy->selfPickupStations, $lineCopy->groupConfig, $lineCopy->icon);

                    if ($countWeight === 0 || $this->isWeightMatched($lineCopy, $countWeight)) {
                        if ($lineCopy->region->name != '全国区域') {
                            $lineCopy->name = $lineCopy->name . '-' . $lineCopy->region->name;
                        }
                        $res[] = $lineCopy->toArray();
                    }
                }
            }
        }

        if ($res === [] && isset($countWeight)) {
            if ($isThrown) {
                //当前计费重量/体积 0kg 暂无可用线路
                return ApiResponseService::success($res, message: 'Current billed weight/volume: ' . ($countWeight / 1000) . 'kg, No lines available!');
            } else {
                return [];
            }
        }

        $res = self::userAuth(collect($res));

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

        $country = Country::query()->findOrFail($payload['country_id']);

        //最后按线路价格升序排列
        $res = $res->map(function ($value) use ($country, $payload) {
            $value['remark'] = str_replace('%', '％', $value['remark']);
            $referenceTime = $value['region']?->reference_time ?? '';
            if ($referenceTime) {
                $value['reference_time'] = $referenceTime;
            }
            $value['delivery_min_days'] = $value['regions']->min('delivery_min_days');
            $value['delivery_max_days'] = $value['regions']->min('delivery_max_days');
            return $value;
        })->sortBy(function ($value) {
            return $value['expire_fee'];
        })->values()->all();

        return $isReturn ? ApiResponseService::success($res, message: 'Success') : $res;
    }

    public function query_client(array $request, bool $isReturn = true, bool $isThrown = true): JsonResponse|array
    {
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
            })->when(isset($payload['only_for_stg']), function (Builder $query) use ($payload) {
                $query->whereHas('group', function ($query) use ($payload) {
                    $query->where('only_for_stg', $payload['only_for_stg']);
                });
            })->when(!($payload['is_great_value'] ?? 0) && ($payload['is_group'] ?? 0) != 1, function (Builder $query) use ($payload) {
                if (empty($payload['package_ids'])) {
                    return;
                }
                //只在下单时过滤，在非超值线路的条件下，过滤只包含拼团渠道的线路
                $query->whereHas('group', function ($query) {
                    $query->where('only_for_group', 0);
                });
            })->when($payload['is_great_value'] ?? null, function (Builder $query) use ($payload) {
                $query->where('is_great_value', $payload['is_great_value']);
            })->when(isset($payload['is_delivery']), function (Builder $query) use ($payload) {
                $query->whereIn('is_delivery', [$payload['is_delivery'], 2]);
            })->when($payload['group_id'] ?? null, function (Builder $query) use ($payload) {
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

                    //过滤超重的运费模板
                    if ($this->filterOverweight($region, $countWeight)) continue;

                    $expressLine->region = ExpressLineRegionInfo::make($region);

                    $expressLine->count_weight = $countWeight;

                    [$expressLine->count_first, $expressLine->count_next] = $fee = $expressLine
                        ->getExpressFeeNew($region, $countWeight, true, true, useVolume: $useVol);
                    $expressLine->expire_fee = array_sum($fee);

                    unset($expressLine->priceGrade, $expressLine->priceRules, $expressLine->preics);
                    unset($expressLine->group, $expressLine->props, $expressLine->defaultStation, $expressLine->selfPickupStations, $expressLine->groupConfig, $expressLine->icon);

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

                //过滤超重的运费模板
                if ($this->filterOverweight($region, $countWeight)) continue;

                $fee = [$expressLine->count_first, $expressLine->count_next] = $expressLine
                    ->getExpressFeeNew($region, $countWeight, true, true, user: $user, useVolume: $useVol);

                $expressLine->region = ExpressLineRegionInfo::make($region);
                $expressLine->expire_fee = array_sum($fee);

                unset($expressLine->priceGrade, $expressLine->priceRules, $expressLine->prices);
                unset($expressLine->group, $expressLine->props, $expressLine->defaultStation, $expressLine->selfPickupStations, $expressLine->groupConfig, $expressLine->icon);

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
                    $matchedRegions = collect([$expressLine->regions->firstWhere('enabled', 1)]);
                } else {
                    $matchedRegions = $expressLine->getRegionByAreas(
                        $payload['country_id'] ?? 0,
                        $payload['area_id'] ?? null,
                        $payload['sub_area_id'] ?? null,
                        $payload['postcode'] ?? '',
                        $this->isStation,
                        $this->withPostArea
                    );
                }

                // 如果没有匹配到任何区域，则跳过
                if ($matchedRegions->isEmpty()) {
                    continue;
                }

                // 保存原始区域数据用于后续处理
                $allRegions = $expressLine->regions;

                // 遍历所有匹配的区域
                foreach ($matchedRegions as $region) {
                    $lineCopy = clone $expressLine;

                    unset($region->expressLine, $lineCopy->regions);

                    $lineCopy->regions = ExpressLinePriceRegionList::collection($allRegions);

                    $region = $region->load([
                        'prices', 'rules.conditions.userAddressTags', 'servicePrices', 'servicePrices.service',
                        'areas', 'postcodeAreas', 'rules.conditions.remoteTypes',
                    ]);

                    if ($this->filterOverweight($region, $countWeight)) continue;

                    $lineCopy->region = ExpressLineRegionInfo::make($region);

                    if (!($payload['only_for_stg'] ?? 0)) {
                        if ($lineCopy->base_mode === ExpressLineModel::BASE_MODE_VOLUME
                            && (!($payload['is_great_value'] ?? 0) || !($payload['is_group'] ?? 0))
                        ) {
                            continue;
                        }
                    }

                    if ($countWeight) {
                        [$lineCopy->count_first, $lineCopy->count_next] = $lineCopy
                            ->getExpressFeeNew(
                                $region,
                                $countWeight,
                                true,
                                true,
                                useVolume: false
                            );

                        $lineCopy->expire_fee = array_sum([$lineCopy->count_first, $lineCopy->count_next]);
                    } else {
                        [$lineCopy->count_first, $lineCopy->count_next] = [0, 0];
                        $lineCopy->expire_fee = 0;
                    }

                    unset($lineCopy->priceGrade, $lineCopy->priceRules, $lineCopy->prices);
                    unset($lineCopy->group, $lineCopy->props, $lineCopy->defaultStation, $lineCopy->selfPickupStations, $lineCopy->groupConfig, $lineCopy->icon);

                    if ($countWeight === 0 || $this->isWeightMatched($lineCopy, $countWeight)) {
                        if ($lineCopy->region->name != '全国区域') {
                            $lineCopy->name = $lineCopy->name . '-' . $lineCopy->region->name;
                        }
                        $res[] = $lineCopy->toArray();
                    }
                }
            }
        }

        if ($res === [] && isset($countWeight)) {
            if ($isThrown) {
                //当前计费重量/体积 0kg 暂无可用线路
                return ApiResponseService::success($res, message: 'Current billed weight/volume: ' . ($countWeight / 1000) . 'kg, No lines available!');
            } else {
                return [];
            }
        }

        $res = self::userAuth(collect($res));

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

        $country = Country::query()->findOrFail($payload['country_id']);

        //最后按线路价格升序排列
        $res = $res->map(function ($value) use ($country, $payload) {
            $value['remark'] = str_replace('%', '％', $value['remark']);
            $referenceTime = $value['region']?->reference_time ?? '';
            if ($referenceTime) {
                $value['reference_time'] = $referenceTime;
            }
            $value['delivery_min_days'] = $value['regions']->min('delivery_min_days');
            $value['delivery_max_days'] = $value['regions']->min('delivery_max_days');
            return $value;
        })->sortBy(function ($value) {
            return $value['expire_fee'];
        })->values()->all();

        return $isReturn ? ApiResponseService::success($res, message: 'Success') : $res;
    }

    /**
     * 处理用户登录和未登录状态的渠道线路价格查询
     * @param Collection $res
     * @param $isThrow
     * @return Collection
     * @throws Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/10/30 15:38
     */
    public static function userAuth(Collection $res, $isThrow = true): Collection
    {
        //兼容用户未登录时查询渠道路线价格
        $user = null;
        if (auth()->check()) {
            $user = auth()->user();

            if (!$user) {
                $user = auth('api')->user();
            }
            if ($user instanceof Admin) {
                return $res;
            }
        }

        if (!$user) {
            $res = $res->filter(fn($expressLine) => $expressLine['auth_target'] == ExpressLineModel::AUTH_ALL);
        } elseif ($user instanceof User) {
            $userTagIds = $user->tags()->get()->modelKeys();
            $user->user_group_id = Custom::where('id', $user->custom_id)->value('group_id');

            $res = $res->filter(function ($expressLine) use ($user,$userTagIds) {
                $epl = is_array($expressLine) ? $expressLine : $expressLine->toArray();
                return ($expressLine['auth_target'] == ExpressLineModel::AUTH_ALL)
                    || in_array($user->user_group_id, array_column($epl['auth_user_groups'] ?? [], 'id'))
                    || in_array($user->member?->level_id, array_column($epl['auth_member_levels'] ?? [], 'id'))
                    || array_intersect($userTagIds, array_column($epl['auth_user_tags'] ?? [], 'id'))
                    || in_array($user->getRawOriginal('id'), array_column($epl['auth_users'] ?? [], 'id'));
            });
        } else {
            $res = collect([]);
        }

        if ($isThrow && $res->isEmpty()) {
            throw new AccidentException('No line is available in the current area!', Code::OPERATE_FAIL);
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

    /**
     * @param array $request
     * @return array
     * @throws Exception
     * @throws ValidationException
     */
    public function orderCreateQuery(array $request): array
    {
        info('当前传入的参数为:', $request);

        $payload = validator($request, [
            'address_ids' => 'nullable',
            'prop_ids' => ['nullable'],
            'package_ids' => ['nullable'],
            'is_great_value' => ['nullable'],
            'warehouse_id' => ['nullable'],
            'type' => 'sometimes|nullable',
            'is_group' => 'sometimes|nullable',
            'ignore_min_weight' => 'sometimes|nullable',
            'ignore_weight' => 'sometimes|nullable',
            'icon_filter' => 'sometimes|nullable',
            'is_delivery' => 'sometimes|nullable',
            'floor' => 'sometimes|nullable|integer|gt:0',
            'station_id' => 'sometimes|nullable',
            'weight' => 'sometimes|nullable|numeric',
            'length' => 'sometimes|nullable|numeric',
            'width' => 'sometimes|nullable|numeric',
            'height' => 'sometimes|nullable|numeric',
            'address' => 'sometimes|nullable|array',
        ])->validate();

        $expressLines = ExpressLineModel::with('icon:id,icon,name')
            ->with('props:id,name')
            ->with('regions.areas')
            ->with('prices:id,express_line_id,type,start,end,price,unit_weight')
            ->with('priceRules:id,express_line_id,type,start,end,unit_weight')
            ->with('defaultStation:id,name,country_id,address,contactor,contact_info')
            ->with('selfPickupStations:id,name,country_id,address,contactor,contact_info')
            ->with('defaultStation.country')
            ->with('warehouses:id,warehouse_name,receiver_name,timezone,phone,postcode,address,tips,enabled')
            ->with('authUserGroups:id')
            ->with('authMemberLevels:id')
            ->with('authUserTags:id')
            ->with('authUsers:id')
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
                $query->whereHas('groupConfig', function ($query) {
                    $query->where('is_group', 1);
                });
            })->when($payload['is_great_value'] ?? null, function (Builder $query) use ($payload) {
                $query->where('is_great_value', $payload['is_great_value']);
            })->when(isset($payload['is_delivery']), function (Builder $query) use ($payload) {
                $query->whereIn('is_delivery', [$payload['is_delivery'], 2]);
            })
            ->whereHas('regions', function ($query) {
                $query->where('enabled', 1);
            })
            ->whereHas('group', function ($query) {
                $query->where('enabled', 1);
            })
            ->where('enabled', 1)
            ->where('is_hidden', 0)
            ->get();

        // 通过多组地址确定区域
        if ($payload['address_ids'] ?? []) {
            $expressLines = $this->filterByAddresses($expressLines, $payload['address_ids']);
        } elseif ($payload['address'] ?? []) {
            $expressLines = $this->filterByRegion($expressLines, $payload['address']);
        }

        if ($expressLines->isEmpty()) {
            throw new AccidentException('No line is available in the current area!', Code::OPERATE_FAIL);
        }

        $expressLines = $expressLines->when($payload['warehouse_id'] ?? null, function ($expressLines) use ($payload) {
            return $expressLines->filter(function ($value) use ($payload) {
                return $value->warehouses
                    ->filter(fn($w) => $w->enabled === 1)
                    ->contains(fn($w) => $payload['warehouse_id'] == $w->id);
            });
        });

        if ($payload['warehouse_id'] ?? null) {
            $warehouseStr = WarehouseAddress::query()->find($payload['warehouse_id'])->warehouse_name ?? '';
            if ($expressLines->isEmpty()) {
                throw new AccidentException('当前仓库:' . $warehouseStr . '暂无可用线路!', Code::OPERATE_FAIL);
            }
        }

        $expressLines = $expressLines->when($payload['prop_ids'] ?? null, function ($expressLines) use ($payload) {
            if (is_string($payload['prop_ids'])) {
                $payload['prop_ids'] = explode(',', $payload['prop_ids']);
            }

            return $expressLines->filter(function ($value) use ($payload) {
                return array_diff($payload['prop_ids'], $value->props->modelKeys()) === [];
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

        if ($payload['package_ids'] ?? null || $payload['weight'] ?? null) {
            if (!empty($payload['package_ids'])) {
                $packages = Package::whereIn('id', $payload['package_ids'])->get();
            }

            /** @var ExpressLineModel $expressLine */
            foreach ($expressLines as $expressLine) {
                if (!empty($packages)) {
                    if ($expressLine->base_mode === ExpressLineModel::BASE_MODE_WEIGHT) {
                        $countWeight = $this->getExpectedWeight($expressLine, $packages) * 1000;
                        info('包裹合计重量为:' . $countWeight);
                        if($expressLine->payment_weight_int){
                            $countWeight = _roundDownTo($countWeight);
                            info('包裹合计重量向下取值后为:' . $countWeight);
                        }
                    } else {
                        $countWeight = $this->getExpectedVolume($expressLine, $packages);
                    }
                } else {
                    if ($expressLine->base_mode === ExpressLineModel::BASE_MODE_WEIGHT) {
                        $countWeight = max(
                                $payload['weight'],
                                $payload['length'] * $payload['width'] * $payload['height'] / $expressLine->factor
                            ) * 1000;
                        info('重量为:' . $countWeight);
                        if($expressLine->payment_weight_int){
                            $countWeight = _roundDownTo($countWeight);
                            info('重量为:' . $countWeight);
                        }
                    } else {
                        $countWeight = $this->getVolume($expressLine, $payload);
                    }
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

                $region = $expressLine->regions->firstWhere('enabled', 1);

                $expressLine->region = ExpressLineRegionList::make($region);

                unset($expressLine->regions);

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
            $res = $expressLines->all();
        }

        if ($res === [] && isset($countWeight)) {
            //当前计费重量/体积 0kg 暂无可用线路
            throw new AccidentException('Current billed weight/volume: ' . ($countWeight / 1000) . 'kg, No lines available!', Code::OPERATE_FAIL);
        }

        $res = self::userAuth(collect($res));

        //最后按线路价格升序排列
        $res = $res->map(function ($value) {
            $value['remark'] = str_replace('%', '％', $value['remark']);
            //unset($value['userGroups'], $value['memberLevels'], $value['users']);
            return $value;
        })->values()->all();

        return ApiResponseService::success($res, message: 'Success');
    }

    /**
     * @param int $regionId
     * @param int $countWeight
     * @return array
     * @throws Exception
     */
    public static function priceTest(int $regionId, int $countWeight, float $profitValue = 0): array
    {
        /** @var ExpressLineModel $expressLine */
        $expressLine = ExpressLineModel::with('icon:id,icon,name')
            ->with('props:id,name')
            ->with('labels:id,name')
            ->with('regions.areas')
            ->with('prices:id,express_line_id,type,start,end,price,unit_weight,first_weight')
            ->with('priceRules:id,express_line_id,type,start,end,unit_weight')
            ->with('defaultStation:id,name,country_id,address,contactor,contact_info')
            ->with('selfPickupStations:id,name,country_id,address,contactor,contact_info,limit_one_weight,limit_many_weight,limit_length')
            ->with('defaultStation.country')
            ->with('warehouses:id,warehouse_name,receiver_name,timezone,phone,postcode,address,tips,enabled')
            ->whereHas('regions', function ($query) use ($regionId) {
                $query->where('id', $regionId);
            })
            ->where('is_hidden', 0)
            ->first();

        $region = ExpressLineRegion::query()->findOrFail($regionId);

        //忽略最小重量时重量上浮为最小重量
        if ($countWeight > 0 && $countWeight < $expressLine->min_weight && $expressLine->ceil_weight) {
            $countWeight = $expressLine->min_weight;
        }

        //计费重量上浮
        if ($expressLine->weight_rise) {
            $countWeight = _ceilTo($countWeight, $expressLine->weight_rise);
        }

        $expressLine->count_weight = $countWeight;

        unset($expressLine->regions, $region->expressLine);

        $expressLine->region = ExpressLineRegionInfo::make($region->load([
            'prices', 'rules.conditions', 'servicePrices', 'servicePrices.service', 'areas', 'postcodeAreas',
            'rules.conditions.remoteTypes', 'rules.conditions.userAddressTags'
        ]));
        $currencyConverter = new CurrencyConverter();

        if ($countWeight) {
            [$expressLine->count_first, $expressLine->count_next] = $expressLine
                ->getExpressFeeNew(
                    $region,
                    $countWeight,
                    true,
                    true,
                    useVolume: $expressLine->base_mode === ExpressLineModel::BASE_MODE_VOLUME
                );


            $expressLine->expire_fee = array_sum([$expressLine->count_first, $expressLine->count_next]) / 100;//这里要除以100
            $expressLine->origin_logistics_fee = $expressLine->expire_fee;
            $expressLine->expire_fee = $currencyConverter->reversedCurrenciesExchange($expressLine->expire_fee); // 物流费用转为USD
            info('多少美元'.$expressLine->expire_fee);
        } else {
            [$expressLine->count_first, $expressLine->count_next] = [0, 0];
            $expressLine->expire_fee = 0;
        }

        unset($expressLine->priceGrade, $expressLine->priceRules, $expressLine->prices);

        //找出基础配置里的物流报价默认配置
        $keyList = [
            SystemConfig::FREIGHT_QUOTE_AMOUNT_TYPE,
            SystemConfig::FREIGHT_QUOTE_CALCULATE_METHOD,
            SystemConfig::FREIGHT_QUOTE_DEFAULT_PROFIT_RATE,
            SystemConfig::FREIGHT_QUOTE_DEFAULT_FIXED_AMOUNT,
        ];

        $systemConfig = SystemConfigService::getMultipleConfig($keyList);
        $amountType = $systemConfig['freight_quote_amount_type'];
        $calculateMethod = $systemConfig['freight_quote_calculate_method'];
        $profitRate = $systemConfig['freight_quote_default_profit_rate'];
        $fixedAmount = $systemConfig['freight_quote_default_fixed_amount'];

        if($amountType == CustomsQuoteConfig::FREIGHT_QUOTE_AMOUNT_TYPE_2){

            if($calculateMethod == CustomsQuoteConfig::FREIGHT_QUOTE_CALCULATE_METHOD_1){
                #按百分比计算：物流成本 / (1 - 利润率) / 汇率

                $freightProfitRate = ($profitValue > 0) ? $profitValue : $profitRate;

                $quote = $expressLine->origin_logistics_fee / (1 - (float) $freightProfitRate / 100);

                //转换成美元
                $expressLine->expire_fee = $currencyConverter->reversedCurrenciesExchange($quote);
            }

            if($calculateMethod == CustomsQuoteConfig::FREIGHT_QUOTE_CALCULATE_METHOD_2){
                #按固定金额计算：(物流成本 + 固定金额(人民币)) / 汇率

                $freightFixedAmount = ($profitValue > 0) ? $profitValue : $fixedAmount;

                $quote = $expressLine->origin_logistics_fee + (float) $freightFixedAmount;

                //转换成美元
                $expressLine->expire_fee = $currencyConverter->reversedCurrenciesExchange($quote);
            }
        }


        return ApiResponseService::success($expressLine->toArray(), message: 'Success');
    }

    /**
     * @param $expressLines
     * @param array $data
     * @return mixed
     */
    protected function filterByRegion($expressLines, array $data)
    {
        return $expressLines->filter(function ($value) use ($data) {
            $regions = $value->regions->filter(function (ExpressLineRegion $v) {
                // 已经启用 且 包含非零的价格即认为已经设置好了
                return $v->enabled && $v->prices->filter(fn($p) => $p->price >= 0)->count();
            });

            foreach ($regions as $region) {
//                if ($region->type === ExpressLineRegion::TYPE_AREA) {
                if ($region->areas->contains(function ($v) use ($data) {
                    return ExpressLineModel::verifyArea($v, $data);
                })) {
                    return true;
                }
//                } else {
//                    if ($region->country_id == $data['country_id']
//                        && ($region->postcodeAreas->contains(function ($area) use ($data) {
//                            if ($area->type === ExpressLineRegionPostcodeArea::TYPE_RANGE) {
//
//                                //判断加拿大邮编范围做特殊处理
//                                if ($data['country_id'] == ExpressLineRegionPostcodeAreaModel::CANADA_COUNTRY_ID) {
//                                    $validator = Validator::make($data, [
//                                        'postcode' => ['required', new CanadianPostalCodeRange($area->start, $area->end)],
//                                    ]);
//
//                                    return $validator->passes();
//                                } else {
//                                    return postcode_integer($data['postcode'] ?? '') >= postcode_integer($area->start)
//                                        && postcode_integer($data['postcode'] ?? '') <= postcode_integer($area->end);
//                                }
//
//                            } elseif ($area->type === ExpressLineRegionPostcodeArea::TYPE_FIXED) {
//                                return ($data['postcode'] ?? '') == $area->start && !$area->end;
//                            }
//
//                            return false;
//                        })
//                        || $this->withPostArea)
//                    ) {
//                        return true;
//                    }
//                }
            }

            return false;
        });
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
     * @param Collection $expressLines
     * @param array $ids
     * @return Collection
     */
    protected function filterByAddresses(Collection $expressLines, array $ids): Collection
    {
        $addresses = UserAddress::query()->whereKey($ids)->get();

        // 对于对个地址的情况 只要是这个线路有区域满足所有地址
        // 并不需要一个区域满足所有地址
        // 所以可以对每个地址做一个标记
        // 如果到最后所有地址都是是成功匹配的
        // 那么可以说这个渠道可以使用
        return $expressLines->filter(function ($value) use ($addresses, $ids) {
            $regions = $value->regions->filter(fn($v) => $v->enabled);

            $enabled = [];
            foreach ($regions as $region) {
                if ($region->type === ExpressLineRegion::TYPE_AREA) {
                    $region->areas->each(function ($v) use ($addresses, &$enabled) {
                        foreach ($addresses as $address) {
                            $res = ExpressLineModel::verifyArea($v, $address);

                            if ($res) {
                                $enabled[] = $address->id;
                            }
                        }
                        return true;
                    });
                } else {
                    $region->postcodeAreas->each(function ($area) use ($region, $addresses, &$enabled) {
                        foreach ($addresses as $address) {
                            $res = postcode_integer($address['postcode']) >= postcode_integer($area->start)
                                && postcode_integer($address['postcode']) <= postcode_integer($area->end)
                                && $region->country_id == $address['country_id'];

                            if ($res) {
                                $enabled[] = $address->id;
                            }
                        }
                        return true;
                    });
                }
            }

            return !array_diff($ids, $enabled);
        });
    }

    /**
     * @param int $id
     * @return array
     */
    public function info(int $id): array
    {
        $data = ExpressLineModel::query()->with('icon:id,icon,name')
            ->with('props:id,name')
            ->with('labels:id,name')
            ->with('regions:id,enabled,express_line_id,name,reference_time')
            ->with('regions.prices:id,region_id,start,end,unit_weight,price,type,first_weight')
            ->with('regions.servicePrices.service')
            ->with('regions.rules.conditions', 'regions.areas.country', 'regions.postcodeAreas')
            ->with('regions.rules.conditions.userAddressTags')
            ->with('defaultStation:id,name,country_id,address,contactor,contact_info')
            ->with('selfPickupStations', function ($query) {
                $query->orderBy('index')->with(['country', 'area', 'subArea']);
            })
            //->with('selfPickupStations.country', 'selfPickupStations.area', 'selfPickupStations.subArea')
            ->with('defaultStation.country')
            ->with('warehouses:id,warehouse_name,receiver_name,timezone,phone,postcode,address,tips,enabled')
            ->findOrFail($id);

        return ApiResponseService::success(ExpressLineInfo::make($data));
    }

    /**
     * @return array
     */
    public function ruleConditions(): array
    {
        return ApiResponseService::success(collect(ExpressLineRuleCondition::rules())->map(function ($value, $key) {
            return [
                'id' => $key,
                'value' => $value,
            ];
        })->values()->all());
    }

    /**
     * @return array
     */
    public function expressLineGreatValueCountries(): array
    {
        $regions = ExpressLineModel::query()->with('regions', function ($query) {
            $query->where('enabled', 1);
        })->where('enabled', 1)
            ->where('is_hidden', 0)
            ->where('is_great_value', 1)
            ->get()->pluck('regions')
            ->values()->flatten()->values();

        $countries = $regions->map(function (ExpressLineRegion $region) {
            $ids = $region->areas->pluck('country_id')->flatten()->values()->all();
            if ($region->type == ExpressLineRegion::TYPE_POSTCODE && $region->country_id) {
                $ids[] = $region->country_id;
            }

            return $ids;
        })->flatten()->unique()->all();

        return ApiResponseService::success(Country::query()->whereKey($countries)->get());
    }

    /**
     * @param $region
     * @param $countWeight
     * @return bool
     */
    protected function filterOverweight($region, $countWeight): bool
    {
        $minWeight = $region->prices->min('start');
        $maxWeight = $region->prices->max('end');

        if ($countWeight < $minWeight || $countWeight >= $maxWeight) return true;

        return false;
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

    /**
     * @param ExpressLineModel $expressLine
     * @param array $data
     * @return int
     */
    public function getVolume(ExpressLineModel $expressLine, array $data): int
    {
        //计算预计体积
        if ($expressLine->weight_factor) {
            $volume = (int)ceil($data['weight'] * 1000 / $expressLine->weight_factor * $expressLine->weight_trans);
        }
        $realVolume = (int)ceil((($data['length'] ?? 0) * ($data['width'] ?? 0) * ($data['height'] ?? 0)) / 1000);

        return max($realVolume, $volume ?? 0);
    }

    /**
     * @return array
     */
    public function groups(): array
    {
        return ApiResponseService::success(ExpressLineGroupsModel::query()->where('enabled', 1)->get());
    }

    /**
     * @param int $id
     * @param PriceDisCountService $service
     * @return array
     */
    public function getDisCount(int $id, PriceDisCountService $service): array
    {
        return ApiResponseService::success($service->query($id));
    }

    /**
     * @param ExpressLineModel $expressLine
     * @param Collection $packages
     * @return int
     */
    public function groupBuyingPackagesCountWeight(ExpressLineModel $expressLine, Collection $packages): float|int
    {
        if ($expressLine->base_mode === ExpressLineModel::BASE_MODE_WEIGHT) {
            $countWeight = $this->getExpectedWeight($expressLine, $packages) * 1000;
            info('包裹合计重量为:' . $countWeight);
            if($expressLine->payment_weight_int){
                $countWeight = _roundDownTo($countWeight);
                info('包裹合计重量向下取值后为:' . $countWeight);
            }
        } else {
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
        // 团购加价系数
        if ($expressLine->groupConfig?->group_raise && $countWeight < $expressLine->groupConfig?->group_raise_threshold) {
            $countWeight = $countWeight * $expressLine->groupConfig?->group_raise;
        }

        return $countWeight;
    }

    /**
     * @param ExpressLineModel $expressLine
     * @param $countWeight
     * @param $region
     * @return array|int
     */
    public function groupBuyingPaymentFee(ExpressLineModel $expressLine, $countWeight, $region): int|array
    {
        try {
            $fee = $expressLine
                ->getExpressFeeNew(
                    $region,
                    $countWeight,
                    false,
                    true,
                    useVolume: $expressLine->base_mode === ExpressLineModel::BASE_MODE_VOLUME
                );
        } catch (\Exception $exception) {
            info('团购计算运费异常:' . $exception->getMessage());
        }

        return $fee ?? 0;
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
}
