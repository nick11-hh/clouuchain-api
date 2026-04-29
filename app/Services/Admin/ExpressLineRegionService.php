<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:40
 */

namespace App\Services\Admin;

ini_set('max_execution_time', 120);

use App\Jobs\Export\ExpressRegionExportJob;
use App\Jobs\Export\RegionPriceExport;
use App\Lib\Code;
use App\Models\Country;
use App\Models\CountryArea;
use App\Models\ExcelExport;
use App\Models\ExpressLineModel;
use App\Models\ExpressLinePrice;
use App\Models\ExpressLinePriceRulesModel;
use App\Models\ExpressLineRegion;
use App\Models\ExpressLineRegionAreasModel;
use App\Models\ExpressLineRegionPostcodeArea;
use App\Models\ExpressLineRegionPostcodeAreaModel;
use App\Models\ExpressLineVAS;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Vtiful\Kernel\Excel;
use App\Exceptions\AccidentException;

class ExpressLineRegionService extends BaseService
{
    use HasNameUniqueValidation;

    public function __construct()
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = new ExpressLineModel();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    /**
     * @param int $id
     * @return LengthAwarePaginator
     */
    // public function regionIndex(int $id)
    // {
    //     $size = \request()->input('size', 10);
    //
    //     return ExpressLineRegion::query()
    //         ->withCount('areas')
    //         ->with('areas', 'postcodeAreas', 'expressLine:id,mode')
    //         ->when(isset($this->formData['keyword']), function ($query) {
    //             $query->where('name', 'like', "%{$this->formData['keyword']}%");
    //         })
    //         ->where('express_line_id', $id)
    //         ->paginate($size);
    // }

    /**
     * @param int $id
     * @return LengthAwarePaginator
     */
    public function regionIndex(int $id)
    {
        $query = ExpressLineRegion::query()
            ->with('expressLine:id,mode', 'country', 'areas', 'postcodeAreas', 'priceRules')
            ->with(['prices' => fn ($q) => $q->orderBy('start')])
            ->where('express_line_id', $id);

        //分区名称
        if ($this->formData['keyword'] ?? '') {
            $query->where('name', 'like', "%{$this->formData['keyword']}%");
        }

        //国家
        if($this->formData['country_id'] ?? '') {
            $query->where(function ($query) {
                $query->whereHas('areas', function ($query) {
                    $query->where('country_id', $this->formData['country_id']);
                })->orWhere('country_id', $this->formData['country_id']);
            });
        }

        $list = $query->paginate($this->formData['size'] ?? 10);

        //修改分页数据
        $list->transform(function ($model) {
            //重量区间带上价格
            $mode = $model->expressLine->mode ?? 0;
            return $this->appendPrices($model, $mode);
        });

        return $list;
    }

    /**
     * @param int $eplId
     * @return Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function regionAll(int $eplId)
    {
        return ExpressLineRegion::query()
            ->select(['id', 'name'])
            ->where('express_line_id', $eplId)
            ->get();
    }

    /**
     * @param int $expressLineId
     * @param int $regionId
     * @return Model
     */
    public function regionInfo(int $expressLineId, int $regionId): Model
    {
        $data = ExpressLineRegion::query()
            ->with('areas', 'postcodeAreas', 'priceRules')
            ->findOrFail($regionId);

        $countryIds = $data['areas']->pluck('country_id')->values();
        $areaIds = $data['areas']->pluck('area_id')->values();
        $subAreaIds = $data['areas']->pluck('sub_area_id')->values();

        $partitions = Country::query()->with('areas', function ($q) use ($areaIds, $subAreaIds) {
            $q->whereKey($areaIds)->with('areas', function ($q) use ($subAreaIds) {
                $q->whereKey($subAreaIds);
            });
        })->whereKey($countryIds)->get();

        $data['partitions'] = $partitions;

        //重量区间带上价格
        $mode = $data->expressLine->mode ?? 0;
        $data = $this->appendPrices($data, $mode);

        return $data;
    }

    public function appendPrices($data, $mode)
    {
        // 首重续重模式
        if ($mode === ExpressLineModel::MODE_1) {
            $data->priceRules->map(function ($rule) use ($data) {
                return $rule->unit_price = $data->prices->where('start', $rule->start)->where('end', $rule->end)->value('price');
            });
        }

        // 阶梯价格模式
        if ($mode === ExpressLineModel::MODE_2) {
            $data->priceRules->map(function ($rule) use ($data) {
                $data->prices->each(function ($item) use ($rule) {
                    if ($item->start === $rule->start && $item->end === $rule->end) {
                        //单价
                        if ($item->type === ExpressLinePrice::TYPE_GRADE_WEIGHT) {
                            $rule->unit_price = $item->price;
                        }

                        //操作费
                        if ($item->type === ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE) {
                            $rule->base_price = $item->price;
                        }
                    }
                });
                return $rule;
            });
        }

        return $data;
    }

    /**
     * 创建线路分区
     *
     * @param int $id
     * @param array $data
     * @return mixed
     * @throws ValidationException
     */
//     public function create(int $id, array $data)
//     {
//         $data = validator($data, $this->rules())->validate();
//
//         //首重续重模式、多重续重必须要设置两条及以上的重量区间数据
//         if($data['mode'] == ExpressLineModel::MODE_1 || $data['mode'] == ExpressLineModel::MODE_GRADE_NEXT){
//             if(count($data['grades']) < 2){
//                 throw new AccidentException('至少需要新增1条以上的重量区间数据', Code::OPERATE_FAIL);
//             }
//         }
//
//         return DB::transaction(function () use ($id, $data) {
//
//             $regionName = ExpressLineRegion::query()->where(['express_line_id' => $id, 'name->zh_CN' => $data['name']])->exists();
//             if ($regionName) {
//                 throw new AccidentException('分区名称已存在', Code::OPERATE_FAIL);
//             }
//
//             /** @var ExpressLineRegion $region */
//             $region = ExpressLineRegion::query()->create([
//                 'express_line_id' => $id,
//                 'name' => $data['name'],
//                 'reference_time' => $data['reference_time'],
//                 'country_id' => $data['country_id'] ?? null,
//                 'type' => $data['type'],
//                 'index' => $data['index'] ?? 0,
//                 'minimum_chargeable_weight' => $data['minimum_chargeable_weight'] * 1000,
//             ]);
//
//             if ($data['type'] == ExpressLineRegion::TYPE_AREA) {
//                 foreach ($data['areas'] as $area) {
//                     if (ExpressLineRegionAreasModel::query()
//                         ->where('express_line_id', $region->express_line_id)
//                         ->where('region_id', '!=', $region->id)
//                         ->where($this->matchRule($area))
//                         ->count()) {
//                         throw new AccidentException('分区区域不能和其他分区重复', Code::OPERATE_FAIL);
//                     }
//
//                     if (ExpressLineRegion::query()
//                         ->where('express_line_id', $region->express_line_id)
//                         ->whereKeyNot($region->id)
//                         ->whereNotNull('country_id')
//                         ->where('country_id', '=', $area['country_id'])
//                         ->count()) {
//                         throw new AccidentException('分区区域不能和其他分区重复', Code::OPERATE_FAIL);
//                     }
//                 }
//
//                 [$countries, $areas] = $this->getCountriesAndAreasData($data);
//
//                 $this->mapAreasData($data, $countries, $areas, $region->express_line_id, $region->id)
//                     ->chunk(50)->each(function ($values) use ($region) {
//                         $region->areas()->insert($values->all());
//                     });
//             } elseif ($data['type'] == ExpressLineRegion::TYPE_POSTCODE) {
//                 if (ExpressLineRegionAreasModel::query()
//                     ->where('express_line_id', $region->express_line_id)
//                     ->where('region_id', '!=', $region->id)
//                     ->where('country_id', '=', $region['country_id'])
//                     ->count()) {
//                     throw new AccidentException('分区区域不能和其他分区重复', Code::OPERATE_FAIL);
//                 }
//
//                 ExpressLineRegion::query()
//                     ->where('express_line_id', $region->express_line_id)
//                     ->whereKeyNot($region->id)
//                     ->whereNotNull('country_id')
//                     ->where('country_id', '=', $region['country_id'])
//                     ->get()->each(function ($r) use ($data) {
//                         $areas = $data['postcodes'] ?? [];
//                         $tAreas = $r->postcodeAreas;
//
//                         foreach ($areas as $area) {
//                             foreach ($tAreas as $t) {
//                                 // 范围重叠
//                                 if (!empty($area['end'])) {
//                                     if (max($area['start'], $t['start']) <= min($area['end'], $t['end'])) {
//                                         throw new AccidentException('邮编范围不能和其他邮编分区重叠', Code::OPERATE_FAIL);
//                                     }
//                                 } else {
//                                     // 离散邮编，邮编相等
//                                     if ($area['start'] == $t['start']) {
//                                         throw new AccidentException('邮编不能和其他邮编分区重复', Code::OPERATE_FAIL);
//                                     }
//                                 }
//                             }
//                         }
//                     });
//
//                $this->verifyPostcodeRanges($data['postcodes'] ?? [], $data['country_id']);
//
//                 $codes = collect($data['postcodes'])->map(function ($p) use ($region, $data) {
//                     if (
//                         $p['type'] === ExpressLineRegionPostcodeAreaModel::TYPE_RANGE &&
//                         $data['country_id'] != ExpressLineRegionPostcodeAreaModel::CANADA_COUNTRY_ID
//                     ) {
//                         preg_match_all('/\d+/', $p['start'], $sMatch);
//                         preg_match_all('/\d+/', $p['end'], $eMatch);
//
//                         $start = collect($sMatch[0])->max();
//                         $end = collect($eMatch[0])->max();
//
//                         if ($start > $end) {
//                             throw new AccidentException('起始邮编不能大于结束邮编', Code::OPERATE_FAIL);
//                         }
//                     }
//
//                     return [
//                         'region_id' => $region->id,
//                         'type' => $p['type'],
//                         'start' => $p['start'],
//                         'end' => $p['end'] ?? '',
//                         'created_at' => now(),
//                         'updated_at' => now(),
//                     ];
//                 })->unique('start')->toArray();
//
//                 $region->postcodeAreas()->insert($codes);
//
//                /*// 固定邮编
//                if (($data['postcodes'][0]['type'] ?? 0 )== ExpressLineRegionPostcodeArea::TYPE_FIXED) {
//                    $codes = collect($data['postcodes'])->map(function ($p) use ($region) {
//                        return [
//                            'region_id' => $region->id,
//                            'type' => ExpressLineRegionPostcodeArea::TYPE_FIXED,
//                            'start' => $p['start'],
//                            'end' => '',
//                            'created_at' => now(),
//                            'updated_at' => now(),
//                        ];
//                    })->unique('start')->toArray();
//
//                    $region->postcodeAreas()->insert($codes);
//                } else {
//                    foreach ($data['postcodes'] as $postcode) {
//                        preg_match_all('/\d+/', $postcode['start'], $sMatch);
//                        preg_match_all('/\d+/', $postcode['end'], $eMatch);
//
//                        $start = collect($sMatch[0])->max();
//                        $end = collect($eMatch[0])->max();
//
//                        if ($start > $end) {
//                            throw new AccidentException('起始邮编不能大于结束邮编', Code::OPERATE_FAIL);
//                        }
//
//                        $region->postcodeAreas()->create([
//                            'type' => $postcode['type'] ?? ExpressLineRegionPostcodeArea::TYPE_RANGE,
//                            'start' => $postcode['start'],
//                            'end' => $postcode['end'],
//                        ]);
//                    }
//                }*/
//             }
//             // 创建区域对应的线路价格
//             // (new ExpressLinePriceService())->initByRegion($region);
//
//             //创建区域对应的增值服务价格
//             $this->initVAS($id, $region);
//
//             //保存分区重量区间
//             $this->saveRegionPricesRule($region->id, $id, $data);
//
//             return true;
//
//         });
//     }

    /**
     * 创建线路分区
     *
     * @param int $expressLineId
     * @param array $data
     * @return mixed
     * @throws ValidationException
     */
    public function create(int $expressLineId, array $data)
    {
        validator($data, $this->rules())->validate();

        return DB::transaction(function () use ($expressLineId, $data) {
            /** @var ExpressLineRegion $region */
            $region = ExpressLineRegion::query()->create([
                'express_line_id' => $expressLineId,
                'name' => $data['type'] === ExpressLineRegion::TYPE_AREA ? '全国区域' : $data['name'],
                'reference_time' => $data['reference_time'],
                'country_id' => $data['country_id'] ?? null,
                'type' => $data['type'],
                'index' => $data['index'] ?? 0,
                'minimum_chargeable_weight' => $data['minimum_chargeable_weight'] * 1000,
                'enabled' => 0,//默认不启用
                'delivery_min_days' => $data['delivery_min_days'] ?? 1, //最小送达天数 默认一天
                'delivery_max_days' => $data['delivery_max_days'] ?? 60, //最大送达天数 默认60天
            ]);

            if ($data['type'] === ExpressLineRegion::TYPE_AREA) {
                foreach ($data['areas'] as $area) {
                    //国家为所有国家时，校验此渠道是否设置了其他国家的价格，存在则不允许创建所有国家的价格表
                    if (empty($area['country_id'])) {
                        //全国区域
                        if (ExpressLineRegionAreasModel::query()
                            ->where('express_line_id', $region->express_line_id)
                            ->where('region_id', '!=', $region->id)
                            ->where('country_id', '!=', 0)
                            ->count()) {
                            throw new AccidentException('该渠道已存在其他国家全国区域的报价，不允许再创建所有国家的报价', Code::OPERATE_FAIL);
                        }

                        //部分区域
                        if (ExpressLineRegion::query()
                            ->where('express_line_id', $region->express_line_id)
                            ->where('id', '!=', $region->id)
                            ->where('country_id', '!=', 0)
                            ->count()) {
                            throw new AccidentException('该渠道已存在其他国家部分区域的报价，不允许再创建所有国家的报价', Code::OPERATE_FAIL);
                        }

                    } else {
                        if (ExpressLineRegionAreasModel::query()
                            ->where('express_line_id', $region->express_line_id)
                            ->where('region_id', '!=', $region->id)
                            ->where('country_id', 0)
                            ->count()) {
                            throw new AccidentException('该渠道已存在所有国家的报价，不允许再创建其他国家的报价', Code::OPERATE_FAIL);
                        }
                    }

                    if (ExpressLineRegionAreasModel::query()
                        ->where('express_line_id', $region->express_line_id)
                        ->where('region_id', '!=', $region->id)
                        ->where($this->matchRule($area))
                        ->count()) {
                        throw new AccidentException('区域为全国区域时，国家不能重复', Code::OPERATE_FAIL);
                    }

                    if (ExpressLineRegion::query()
                        ->where('express_line_id', $region->express_line_id)
                        ->whereKeyNot($region->id)
                        ->whereNotNull('country_id')
                        ->where('country_id', '=', $area['country_id'])
                        ->count()) {
                        throw new AccidentException('该国家已设置了部分区域，不允许再设置全国区域', Code::OPERATE_FAIL);
                    }
                }

                [$countries, $areas] = $this->getCountriesAndAreasData($data);

                $this->mapAreasData($data, $countries, $areas, $region->express_line_id, $region->id)
                    ->chunk(50)->each(function ($values) use ($region) {
                        $region->areas()->insert($values->all());
                    });
            } elseif ($data['type'] === ExpressLineRegion::TYPE_POSTCODE) {

                if (ExpressLineRegionAreasModel::query()
                    ->where('express_line_id', $region->express_line_id)
                    ->where('region_id', '!=', $region->id)
                    ->where('country_id', '=', $region['country_id'])
                    ->count()) {
                    throw new AccidentException('此国家已设置为全国区域，不能再设置分区区域', Code::OPERATE_FAIL);
                }

                //校验分区名称
                $regionName = ExpressLineRegion::query()
                    ->where('express_line_id', $region->express_line_id)
                    ->where('id', '!=', $region->id)
                    ->where('name->zh_CN', $data['name'])
                    ->where('type', ExpressLineRegion::TYPE_POSTCODE)
                    ->count();
                if ($regionName) {
                    throw new AccidentException('区域设置为部分区域时，分区名称不能重复', Code::OPERATE_FAIL);
                }

                ExpressLineRegion::query()
                    ->where('express_line_id', $region->express_line_id)
                    ->whereKeyNot($region->id)
                    ->whereNotNull('country_id')
                    ->where('country_id', '=', $region['country_id'])
                    ->get()->each(function ($r) use ($data) {
                        $areas = $data['postcodes'] ?? [];
                        $tAreas = $r->postcodeAreas;

                        foreach ($areas as $area) {
                            foreach ($tAreas as $t) {
                                // 范围重叠
                                if (!empty($area['end'])) {
                                    if (max($area['start'], $t['start']) <= min($area['end'], $t['end'])) {
                                        throw new AccidentException('邮编范围不能和其他邮编分区重叠', Code::OPERATE_FAIL);
                                    }
                                } else {
                                    // 离散邮编，邮编相等
                                    if ($area['start'] == $t['start']) {
                                        throw new AccidentException('邮编不能和其他邮编分区重复', Code::OPERATE_FAIL);
                                    }
                                }
                            }
                        }
                    });

                $this->verifyPostcodeRanges($data['postcodes'] ?? []);

                $codes = collect($data['postcodes'])->map(function ($p) use ($region) {
                    if ($p['type'] === ExpressLineRegionPostcodeAreaModel::TYPE_RANGE) {
                        /*preg_match_all('/\d+/', $p['start'], $sMatch);
                        preg_match_all('/\d+/', $p['end'], $eMatch);

                        $start = collect($sMatch[0])->max();
                        $end = collect($eMatch[0])->max();

                        if ($start >= $end) {
                            throw new AccidentException('起始邮编必须小于结束邮编', Code::OPERATE_FAIL);
                        }*/

                        $start = $p['start'];
                        $end = $p['end'];
                        if (strcmp($start, $end) > 0) {
                            throw new AccidentException("起始邮编必须小于结束邮编：{$start}-{$end}", Code::OPERATE_FAIL);
                        }
                    }

                    return [
                        'region_id' => $region->id,
                        'type' => $p['type'],
                        'start' => $p['start'],
                        'end' => $p['end'] ?? '',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->unique('start')->toArray();

                $region->postcodeAreas()->insert($codes);
            }

            //保存分区重量区间
            $this->saveRegionPricesRule($region->id, $expressLineId, $data);

            //更新单个分区的重量区间价格
            $this->updateRegionPrices($region->id, $expressLineId, $data);

            return true;
        });
    }

    /**
     * 更新线路区域
     *
     * @param int $id
     * @param int $expressLineId
     * @param array $data
     * @return mixed
     * @throws ValidationException
     */
//     public function update(int $id, int $expressLineId, array $data)
//     {
//         $data = validator($data, $this->rules())->validate();
//
//         //首重续重模式、多重续重必须要设置两条及以上的重量区间数据
//         if($data['mode'] == ExpressLineModel::MODE_1 || $data['mode'] == ExpressLineModel::MODE_GRADE_NEXT){
//             if(count($data['grades']) < 2){
//                 throw new AccidentException('至少需要新增1条以上的重量区间数据', Code::OPERATE_FAIL);
//             }
//         }
//
//         return DB::transaction(function () use ($id, $expressLineId, $data) {
//             /** @var ExpressLineRegion $region */
//             $region = ExpressLineRegion::query()->findOrFail($id);
//
//             $region->update([
//                 'name' => $data['name'],
//                 'reference_time' => $data['reference_time'] ?? '',
//                 'country_id' => $data['country_id'] ?? null,
//                 'type' => $data['type'],
//                 'index' => $data['index'] ?? 0,
//                 'minimum_chargeable_weight' => $data['minimum_chargeable_weight'] * 1000,
//             ]);
//
//             if ($data['type'] == ExpressLineRegion::TYPE_AREA) {
//                 foreach ($data['areas'] as $area) {
//                     if (ExpressLineRegionAreasModel::query()
//                         ->where('express_line_id', $region->express_line_id)
//                         ->where('region_id', '!=', $region->id)
//                         ->where($this->matchRule($area))
//                         ->count()) {
//                         throw new AccidentException('分区区域不能和其他分区重复', Code::OPERATE_FAIL);
//                     }
//
//                     if (ExpressLineRegion::query()
//                         ->where('express_line_id', $region->express_line_id)
//                         ->whereKeyNot($region->id)
//                         ->whereNotNull('country_id')
//                         ->where('country_id', '=', $area['country_id'])
//                         ->count()) {
//                         throw new AccidentException('分区区域不能和其他分区重复', Code::OPERATE_FAIL);
//                     }
//                 }
//
//                 [$countries, $areas] = $this->getCountriesAndAreasData($data);
//
//                 $region->areas()->delete();
//                 $region->postcodeAreas()->delete();
//
//                 $this->mapAreasData($data, $countries, $areas, $region->express_line_id, $region->id)
//                     ->chunk(50)->each(function ($values) use ($region) {
//                         $region->areas()->insert($values->all());
//                     });
//             } else {
//                 if (ExpressLineRegionAreasModel::query()
//                     ->where('express_line_id', $region->express_line_id)
//                     ->where('region_id', '!=', $region->id)
//                     ->where('country_id', '=', $region['country_id'])
//                     ->count()) {
//                     throw new AccidentException('分区区域不能和其他分区重复', Code::OPERATE_FAIL);
//                 }
//
//                 ExpressLineRegion::query()
//                     ->where('express_line_id', $region->express_line_id)
//                     ->whereKeyNot($region->id)
//                     ->whereNotNull('country_id')
//                     ->where('country_id', '=', $region['country_id'])
//                     ->get()->each(function ($r) use ($data) {
//                         $areas = $data['postcodes'] ?? [];
//                         $tAreas = $r->postcodeAreas;
//
//                         foreach ($areas as $area) {
//                             foreach ($tAreas as $t) {
//                                 // 范围重叠
//                                 if (!empty($area['end'])) {
//                                     if (max($area['start'], $t['start']) <= min($area['end'], $t['end'])) {
//                                         throw new AccidentException('邮编范围不能和其他邮编分区重叠: ' . max($area['start'], $t['start']) .' <= ' . min($area['end'], $t['end']), Code::OPERATE_FAIL);
//                                     }
//                                 } else {
//                                     // 离散邮编，邮编相等
//                                     if ($area['start'] == $t['start']) {
//                                         throw new AccidentException('邮编不能和其他邮编分区重复：'.$area['start'], Code::OPERATE_FAIL);
//                                     }
//                                 }
//                             }
//                         }
//                     });
//
//                 $this->verifyPostcodeRanges($data['postcodes'] ?? [], $data['country_id']);
//
//                 $region->areas()->delete();
//
//                 if ($data['postcodes'] ?? []) {
//                     $region->postcodeAreas()->delete();
//                 }
//
//                 $codes = collect($data['postcodes'])->map(function ($p) use ($region, $data) {
//                     if (
//                         $p['type'] === ExpressLineRegionPostcodeAreaModel::TYPE_RANGE &&
//                         $data['country_id'] != ExpressLineRegionPostcodeAreaModel::CANADA_COUNTRY_ID
//                     ) {
//                         preg_match_all('/\d+/', $p['start'], $sMatch);
//                         preg_match_all('/\d+/', $p['end'], $eMatch);
//
//                         $start = collect($sMatch[0])->max();
//                         $end = collect($eMatch[0])->max();
//
//                         if ($start > $end) {
//                             throw new AccidentException('起始邮编不能大于结束邮编', Code::OPERATE_FAIL);
//                         }
//                     }
//
//                     return [
//                         'region_id' => $region->id,
//                         'type' => $p['type'],
//                         'start' => $p['start'],
//                         'end' => $p['end'] ?? '',
//                         'created_at' => now(),
//                         'updated_at' => now(),
//                     ];
//                 })->unique('start')->toArray();
//
//                 $region->postcodeAreas()->insert($codes);
//
//                 /*foreach ($data['postcodes'] as $postcode) {
//                     $postcodeData = [];
//                     if ($postcode['type'] === ExpressLineRegionPostcodeAreaModel::TYPE_RANGE) {
//                         preg_match_all('/\d+/', $postcode['start'], $sMatch);
//                         preg_match_all('/\d+/', $postcode['end'], $eMatch);
//
//                         $start = collect($sMatch[0])->max();
//                         $end = collect($eMatch[0])->max();
//
//                         if ($start >= $end) {
//                             throw new AccidentException('起始邮编必须小于结束邮编', Code::OPERATE_FAIL);
//                         }
//
//                         $postcodeData = [
//                             'type' => $postcode['type'],
//                             'start' => $postcode['start'],
//                             'end' => $postcode['end'],
//                         ];
//                     } else {
//                         // 固定邮编
//                         $postcodeData = [
//                             'type' => $postcode['type'],
//                             'start' => $postcode['start'],
//                             'end' => '',
//                         ];
//                     }
//
//                     $region->postcodeAreas()->create($postcodeData);
//                 }*/
//
//                 /*// 固定邮编
//                 if (($data['postcodes'][0]['type'] ?? 0) == ExpressLineRegionPostcodeAreaModel::TYPE_FIXED) {
//                     $codes = collect($data['postcodes'])->map(function ($p) use ($region) {
//                         return [
//                             'region_id' => $region->id,
//                             'type' => ExpressLineRegionPostcodeAreaModel::TYPE_FIXED,
//                             'start' => $p['start'],
//                             'end' => '',
//                             'created_at' => now(),
//                             'updated_at' => now(),
//                         ];
//                     })->unique('start')->toArray();
//
//                     $region->postcodeAreas()->insert($codes);
//                 } else {
//                     foreach ($data['postcodes'] as $postcode) {
//                         preg_match_all('/\d+/', $postcode['start'], $sMatch);
//                         preg_match_all('/\d+/', $postcode['end'], $eMatch);
//
//                         $start = collect($sMatch[0])->max();
//                         $end = collect($eMatch[0])->max();
//
//                         if ($start > $end) {
//                             throw new AccidentException('起始邮编不能大于结束邮编', Code::OPERATE_FAIL);
//                         }
//
//                         $region->postcodeAreas()->create([
//                             'type' => $postcode['type'] ?? ExpressLineRegionPostcodeAreaModel::TYPE_RANGE,
//                             'start' => $postcode['start'],
//                             'end' => $postcode['end'],
//                         ]);
//                     }
//                 }*/
//             }
//
//             //保存分区重量区间
//             $this->saveRegionPricesRule($id, $expressLineId, $data);
//
//             return true;
//         });
//     }

    /**
     * 更新线路区域
     *
     * @param int $id
     * @param int $expressLineId
     * @param array $data
     * @return mixed
     * @throws ValidationException
     */
    public function update(int $id, int $expressLineId, array $data)
    {
        validator($data, $this->rules())->validate();

        return DB::transaction(function () use ($id, $expressLineId, $data) {
            /** @var ExpressLineRegion $region */
            $region = ExpressLineRegion::query()->findOrFail($id);

            //先更新分区名称
            $region->update([
                'name' => $data['type'] === ExpressLineRegion::TYPE_AREA ? '全国区域' : $data['name'],
                'reference_time' => $data['reference_time'] ?? '', //参考时效
                'country_id' => $data['country_id'] ?? null, //国家空为全国区域
                'type' => $data['type'],
                'index' => $data['index'] ?? 0,
                'minimum_chargeable_weight' => $data['minimum_chargeable_weight'] * 1000, //最小可计费重量
                'delivery_min_days' => $data['delivery_min_days'] ?? 1, //最小送达天数 默认一天
                'delivery_max_days' => $data['delivery_max_days'] ?? 60, //最大送达天数 默认60天
            ]);

            //如果是按区域
            if ($data['type'] == ExpressLineRegion::TYPE_AREA) {
                foreach ($data['areas'] as $area) {
                    //国家为所有国家时，校验此渠道是否设置了其他国家的价格，存在则不允许创建所有国家的价格表
                    if (empty($area['country_id'])) {
                        //全国区域
                        if (ExpressLineRegionAreasModel::query()
                            ->where('express_line_id', $region->express_line_id)
                            ->where('region_id', '!=', $region->id)
                            ->where('country_id', '!=', 0)
                            ->count()) {
                            throw new AccidentException('该渠道已存在其他国家全国区域的报价，不允许再创建所有国家的报价', Code::OPERATE_FAIL);
                        }

                        //部分区域
                        if (ExpressLineRegion::query()
                            ->where('express_line_id', $region->express_line_id)
                            ->where('id', '!=', $region->id)
                            ->where('country_id', '!=', 0)
                            ->count()) {
                            throw new AccidentException('该渠道已存在其他国家部分区域的报价，不允许再创建所有国家的报价', Code::OPERATE_FAIL);
                        }
                    } else {
                        if (ExpressLineRegionAreasModel::query()
                            ->where('express_line_id', $region->express_line_id)
                            ->where('region_id', '!=', $region->id)
                            ->where('country_id', 0)
                            ->count()) {
                            throw new AccidentException('该渠道已存在所有国家的报价，不允许再创建其他国家的报价', Code::OPERATE_FAIL);
                        }
                    }

                    if (ExpressLineRegionAreasModel::query()
                        ->where('express_line_id', $region->express_line_id)
                        ->where('region_id', '!=', $region->id)
                        ->where($this->matchRule($area))
                        ->count()) {
                        throw new AccidentException('区域为全国区域时，国家不能重复', Code::OPERATE_FAIL);
                    }

                    if (ExpressLineRegion::query()
                        ->where('express_line_id', $region->express_line_id)
                        ->whereKeyNot($region->id)
                        ->whereNotNull('country_id')
                        ->where('country_id', '=', $area['country_id'])
                        ->count()) {
                        throw new AccidentException('该国家已设置了部分区域，不允许再设置全国区域', Code::OPERATE_FAIL);
                    }
                }

                [$countries, $areas] = $this->getCountriesAndAreasData($data);

                $region->areas()->delete();
                $region->postcodeAreas()->delete();

                $this->mapAreasData($data, $countries, $areas, $region->express_line_id, $region->id)
                    ->chunk(50)->each(function ($values) use ($region) {
                        $region->areas()->insert($values->all());
                    });
            } else {
                if (ExpressLineRegionAreasModel::query()
                    ->where('express_line_id', $region->express_line_id)
                    ->where('region_id', '!=', $region->id)
                    ->where('country_id', '=', $region['country_id'])
                    ->count()) {
                    throw new AccidentException('分区区域不能和其他分区重复', Code::OPERATE_FAIL);
                }

                //校验分区名称
                $regionName = ExpressLineRegion::query()
                    ->where('express_line_id', $region->express_line_id)
                    ->where('id', '!=', $region->id)
                    ->where('name->zh_CN', $data['name'])
                    ->where('type', ExpressLineRegion::TYPE_POSTCODE)
                    ->count();
                if ($regionName) {
                    throw new AccidentException('区域设置为部分区域时，分区名称不能重复', Code::OPERATE_FAIL);
                }

                ExpressLineRegion::query()
                    ->where('express_line_id', $region->express_line_id)
                    ->whereKeyNot($region->id)
                    ->whereNotNull('country_id')
                    ->where('country_id', '=', $region['country_id'])
                    ->get()->each(function ($r) use ($data) {
                        $areas = $data['postcodes'] ?? [];
                        $tAreas = $r->postcodeAreas;

                        foreach ($areas as $area) {
                            foreach ($tAreas as $t) {
                                // 范围重叠
                                if (!empty($area['end'])) {
                                    if (max($area['start'], $t['start']) <= min($area['end'], $t['end'])) {
                                        throw new AccidentException('邮编范围不能和其他邮编分区重叠: ' . max($area['start'], $t['start']) .' <= ' . min($area['end'], $t['end']), Code::OPERATE_FAIL);
                                    }
                                } else {
                                    // 离散邮编，邮编相等
                                    if ($area['start'] == $t['start']) {
                                        throw new AccidentException('邮编不能和其他邮编分区重复：'.$area['start'], Code::OPERATE_FAIL);
                                    }
                                }
                            }
                        }
                    });

                $this->verifyPostcodeRanges($data['postcodes'] ?? []);

                $region->areas()->delete();

                if ($data['postcodes'] ?? []) {
                    $region->postcodeAreas()->delete();
                }

                $codes = collect($data['postcodes'])->map(function ($p) use ($region) {
                    if ($p['type'] === ExpressLineRegionPostcodeAreaModel::TYPE_RANGE) {
                        /*preg_match_all('/\d+/', $p['start'], $sMatch);
                        preg_match_all('/\d+/', $p['end'], $eMatch);

                        $start = collect($sMatch[0])->max();
                        $end = collect($eMatch[0])->max();

                        if ($start >= $end) {
                            throw new AccidentException('起始邮编必须小于结束邮编', Code::OPERATE_FAIL);
                        }*/

                        $start = $p['start'];
                        $end = $p['end'];
                        if (strcmp($start, $end) > 0) {
                            throw new AccidentException("起始邮编必须小于结束邮编：{$start}-{$end}", Code::OPERATE_FAIL);
                        }
                    }

                    return [
                        'region_id' => $region->id,
                        'type' => $p['type'],
                        'start' => $p['start'],
                        'end' => $p['end'] ?? '',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->unique('start')->toArray();

                $region->postcodeAreas()->insert($codes);
            }

            //保存分区重量区间
            $this->saveRegionPricesRule($id, $expressLineId, $data);

            //更新单个分区的重量区间价格
            $this->updateRegionPrices($id, $expressLineId, $data);

            return true;
        });
    }

    /**
     * @param int $id
     * @return bool
     */
    public function destroy(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            /** @var ExpressLineRegion $region */
            $region = ExpressLineRegion::query()->findOrFail($id);

            $region->prices()->delete();
            $region->servicePrices()->delete();
            $region->areas()->delete();

            return $region->delete();
        });
    }

    /**
     * 批量删除线路分区
     * @param $params
     * @return true
     * @throws ValidationException
     */
    public function batchDestroy($params)
    {
        validator($params,  [
            'ids' => 'required|array',
        ])->validate();

        foreach ($params['ids'] as $id) {
            $this->destroy((int)$id);
        }

        return true;
    }

    /**
     * 设置状态
     *
     * @param int $id
     * @param bool $status
     * @return bool
     * @throws Exception
     */
    public function setStatus(int $id, bool $status): bool
    {
        /** @var ExpressLineRegion $region */
        $region = ExpressLineRegion::with('prices')->findOrFail($id);

        if ($region->prices->filter(fn($p) => $p->price)->count() === 0 && $status) {
            throw new AccidentException('当前区域价格未配置，请先到价格表配置价格再开启', Code::OPERATE_FAIL);
        }

        return $region->update(
            [
                'enabled' => (int)$status,
            ]
        );
    }

    /**
     * @param int $id
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function updateTranslateData(int $id, array $data)
    {
        validator($data, $this->translateRules())->validate();

        /** @var ExpressLineRegion $region */
        $region = ExpressLineRegion::query()->findOrFail($id);

        $region->setTranslations('name', [$data['language'] => $data['name'] ?? '']);
        $region->setTranslations('reference_time', [$data['language'] => $data['reference_time']]);

        return $region->save();
    }

    /**
     * 从模板复制
     *
     * @param int $expId
     * @param int $templateId
     * @return mixed
     */
    public function copy(int $expId, int $templateId)
    {
        /** @var RegionTemplate $tmp */
        $tmp = RegionTemplate::query()
            ->with(['regions', 'regions.areas', 'regions.postcodeAreas'])
            ->findOrFail($templateId);

        return DB::transaction(function () use ($expId, $tmp) {
            $tmp->regions->each(function ($tRegion) use ($expId) {
                if ($tRegion->type === ExpressLineRegionTemplate::TYPE_AREA) {
                    $tRegion->areas->each(function ($area) use ($expId) {
                        if (ExpressLineRegionAreasModel::query()
                            ->where('express_line_id', $expId)
                            ->where([
                                ['country_id', '=', $area['country_id']],
                                ['area_id', '=', $area['area_id'] ?? null],
                                ['sub_area_id', '=', $area['sub_area_id'] ?? null],
                            ])
                            ->count()) {
                            throw new AccidentException('模板的分区区域不能和已存在分区重复', Code::OPERATE_FAIL);
                        }
                    });
                    /** @var ExpressLineRegion $region */
                    $region = ExpressLineRegion::query()->create([
                        'express_line_id' => $expId,
                        'name' => $tRegion->getTranslations('name'),
                        'reference_time' => $tRegion->getTranslations('reference_time'),
                        'index' => $tRegion->index ?? 0,
                    ]);
                    /** @var Collection $areas */
                    $areas = $tRegion->areas;

                    $areas->map(function ($area) use ($expId, $region) {
                        return [
                            'region_id' => $region->getKey(),
                            'express_line_id' => $expId,
                            'country_id' => $area['country_id'],
                            'country_name' => json_encode($area->getTranslations('country_name')),
                            'created_at' => now()->toDateTimeString(),
                            'updated_at' => now()->toDateTimeString(),
                            'area_id' => $area->area_id,
                            'area_name' => json_encode($area->getTranslations('area_name')),
                            'sub_area_id' => $area->sub_area_id,
                            'sub_area_name' => json_encode($area->getTranslations('sub_area_name')),
                        ];
                    })->chunk(50)->each(fn($items) => $region->areas()->insert($items->all()));
                } else {
                    if (ExpressLineRegionAreasModel::query()
                        ->where('express_line_id', $expId)
                        ->where('country_id', '=', $tRegion['country_id'])
                        ->count()) {
                        throw new AccidentException('模板的分区区域不能和已存在分区重复', Code::OPERATE_FAIL);
                    }

                    if (ExpressLineRegion::query()
                        ->where('express_line_id', $expId)
                        ->where('country_id', '=', $tRegion['country_id'])
                        ->count()) {
                        throw new AccidentException('模板的邮编区域不能和现有分区重复', Code::OPERATE_FAIL);
                    }

                    /** @var ExpressLineRegion $region */
                    $region = ExpressLineRegion::query()->create([
                        'express_line_id' => $expId,
                        'name' => $tRegion->getTranslations('name'),
                        'reference_time' => $tRegion->getTranslations('reference_time'),
                        'country_id' => $tRegion->country_id,
                        'type' => $tRegion['type'],
                    ]);
                    /** @var Collection $areas */
                    $areas = $tRegion->postcodeAreas;

                    $areas->map(function ($area) use ($expId, $region) {
                        return [
                            'region_id' => $region->getKey(),
                            'type' => $area['type'],
                            'start' => $area['start'],
                            'end' => $area['end'],
                            'created_at' => now()->toDateTimeString(),
                            'updated_at' => now()->toDateTimeString(),
                        ];
                    })->chunk(50)->each(fn($items) => $region->postcodeAreas()->insert($items->all()));
                }
                // 创建区域对应的线路价格
                (new ExpressLinePriceService())->initByRegion($region);
                //创建区域对应的增值服务价格
                $this->initVAS($expId, $region);

                return true;
            });

            return true;
        });
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function updateIndex(array $data)
    {
        $data = validator($data, [
            '*.id' => 'required',
            '*.index' => 'required|integer',
        ])->validate();

        return DB::transaction(function () use ($data) {
            foreach ($data as $datum) {
                ExpressLineRegion::query()
                    ->findOrFail($datum['id'])
                    ->update(['index' => $datum['index']]);
            }

            return true;
        });
    }

    /**
     * @param $area
     * @return array[]
     */
    protected function matchRule($area)
    {
        return [
            ['country_id', '=', $area['country_id']],
            ['area_id', '=', $area['area_id'] ?? null],
            ['sub_area_id', '=', $area['sub_area_id'] ?? null],
        ];
    }

    /**
     * @param array $data
     * @return array
     */
    protected function getCountriesAndAreasData(array $data)
    {
        $countries = Country::query()
            ->whereKey(array_column($data['areas'], 'country_id'))
            ->select('id', 'name')
            ->get();

        $areaIds = collect($data['areas'])->pluck('area_id')
            ->filter(fn($v) => $v)
            ->all();
        $subAreaIds = collect($data['areas'])->pluck('sub_area_id')->values()
            ->filter(fn($v) => $v)
            ->all();

        $areas = CountryArea::query()
            ->whereKey(array_merge($areaIds, $subAreaIds))
            ->select('id', 'name')
            ->get();

        return [$countries, $areas];
    }

    /**
     * @param array $data
     * @param Collection $countries
     * @param Collection $areas
     * @param int $expressLineId
     * @param int $regionId
     * @return Collection
     */
    protected function mapAreasData(array $data, Collection $countries, Collection $areas, int $expressLineId, int $regionId)
    {
        return collect($data['areas'])->map(function ($item) use ($countries, $areas, $expressLineId, $regionId) {
            $data = [
                'region_id' => $regionId,
                'express_line_id' => $expressLineId,
                'country_id' => $item['country_id'],
                'country_name' => json_encode($countries->firstWhere('id', $item['country_id'])?->getTranslations('name')),
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
                'area_id' => null,
                'area_name' => null,
                'sub_area_id' => null,
                'sub_area_name' => null,
            ];

            if (isset($item['area_id'])) {
                $data = array_merge($data, [
                    'area_id' => $item['area_id'],
                    'area_name' => json_encode($areas
                        ->firstWhere('id', $item['area_id'])
                        ->getTranslations('name')),
                ]);
            }

            if (isset($item['sub_area_id'])) {
                $data = array_merge($data, [
                    'sub_area_id' => $item['sub_area_id'],
                    'sub_area_name' => json_encode($areas
                        ->firstWhere('id', $item['sub_area_id'])
                        ->getTranslations('name')),
                ]);
            }

            return $data;
        });
    }

    /**
     * @param int $id
     * @param ExpressLineRegion $region
     * @return bool
     */
    protected function initVAS(int $id, ExpressLineRegion $region): bool
    {
        //创建区域对应的增值服务价格
        $services = ExpressLineVAS::query()
            ->where('express_line_id', $id)
            ->get();

        $services->each(function ($service) use ($region, $id) {
            $price = [
                'express_line_id' => $id,
                'region_id' => $region->getKey(),
                'service_id' => $service->getKey(),
                'value' => 0,
            ];
            $service->prices()->create($price);
        });

        return true;
    }

    /**
     * @param array|null $ranges
     * @param $countryId
     * @throws Exception
     */
    protected function verifyPostcodeRanges(?array $ranges = null, $countryId = 0)
    {
        if (! $ranges) {
            return;
        }

        for ($i = 0; $i < count($ranges); $i++) {
            if (count($ranges) === ($i + 1)) {
                break;
            }

            for ($k = $i + 1; $k < count($ranges); $k++) {
                // 固定邮编范围不参与校验
                if ($ranges[$i]['type'] == ExpressLineRegionPostcodeAreaModel::TYPE_FIXED) {
                    continue;
                }

                if ($countryId == ExpressLineRegionPostcodeAreaModel::CANADA_COUNTRY_ID) {
                    if (!isCanadaPostCode($ranges[$i]['start']) || !isCanadaPostCode($ranges[$i]['end'])) {
                        throw new AccidentException('选择地区加拿大，输入的邮编非法', Code::OPERATE_FAIL);
                    }
                } else {
                    if (max($ranges[$i]['start'], $ranges[$k]['start']) <= min($ranges[$i]['end'], $ranges[$k]['end'])) {
                        throw new AccidentException('邮编范围不能重叠 '.max($ranges[$i]['start'], $ranges[$k]['start']).' <= '.min($ranges[$i]['end'], $ranges[$k]['end']), Code::OPERATE_FAIL);
                    }
                }
            }
        }
    }

    /**
     * @desc 保存分区重量区间
     * @param int $regionId
     * @param int $expressLineId
     * @param array $data
     * @return mixed
     * @throws ValidationException
     */
    public function saveRegionPricesRule($regionId, $expressLineId, $data)
    {
        /** @var ExpressLineModel $expressLine */
        $expressLine = ExpressLineModel::query()->findOrFail($expressLineId);

        $region = ExpressLineRegion::query()->findOrFail($regionId);

        $data['mode'] = $expressLine->mode;

        $grades = $data['grades'];

        //取表格第一行额数据为首重
        $firstWeight = $grades[0]['end'];

        //首重续重模式/多重续重模式-删除首重数据
        if ($data['mode'] === ExpressLineModel::MODE_1 || $data['mode'] === ExpressLineModel::MODE_GRADE_NEXT) {
            unset($grades[0]);
        }

        $this->validatePriceGrades($grades, $data['mode'], $firstWeight);

        $newRules = [];
        if ($data['mode'] === ExpressLineModel::MODE_1) {
            $newRules[] = $region->priceRules()
                ->updateOrCreate(
                    [
                        'express_line_id' => $expressLine->id,
                        'type' => ExpressLinePrice::TYPE_FIRST_WEIGHT,
                        'start' => $firstWeight * 1000,
                        'end' => $firstWeight * 1000,
                    ],
                    [
                        'unit_weight' => null,
                    ]
                );

            //先删除首重的重量区间
            unset($data['grades'][0]);

            foreach ($data['grades'] as $grade) {
                $newRules[] = $region->priceRules()
                    ->updateOrCreate(
                        [
                            'express_line_id' => $expressLine->id,
                            'type' => ExpressLinePrice::TYPE_NEXT_WEIGHT,
                            'start' => $grade['start'] * 1000,
                            'end' => $grade['end'] * 1000,
                        ],
                        [
                            'unit_weight' => $grade['unit_weight'] * 1000,
                            'scale_weight' => $grade['scale_weight'] * 1000,
                        ]
                    );
            }
        } elseif ($data['mode'] === ExpressLineModel::MODE_2) {
            foreach ($data['grades'] as $grade) {
                $newRules[] = $region->priceRules()
                    ->updateOrCreate(
                        [
                            'express_line_id' => $expressLine->id,
                            'type' => ExpressLinePrice::TYPE_GRADE_WEIGHT,
                            'start' => $grade['start'] * 1000,
                            'end' => $grade['end'] * 1000,
                        ],
                        [
                            'unit_weight' => null,
                            'scale_weight' => $grade['scale_weight'] * 1000,
                        ]
                    );
            }
        } elseif ($data['mode'] == ExpressLineModel::MODE_MIX) {
            $newRules[] = $region->priceRules()
                ->updateOrCreate(
                    [
                        'express_line_id' => $expressLine->id,
                        'type' => ExpressLinePrice::TYPE_UNIT_WEIGHT,
                        'start' => 0,
                        'end' => 0,
                    ],
                    [
                        'unit_weight' => 1000,
                    ]
                );

            foreach ($data['grades'] as $grade) {
                $newRules[] = $region->priceRules()
                    ->updateOrCreate(
                        [
                            'express_line_id' => $expressLine->id,
                            'type' => ExpressLinePrice::TYPE_GRADE_WEIGHT_APPEND,
                            'start' => $grade['start'] * 1000,
                            'end' => $grade['end'] * 1000,
                        ],
                        [
                            'unit_weight' => null,
                            'scale_weight' => $grade['scale_weight'] * 1000,
                        ]
                    );
            }
        } elseif ($data['mode'] == ExpressLineModel::MODE_GRADE_NEXT) {
            $newRules[] = $region->priceRules()
                ->updateOrCreate(
                    [
                        'express_line_id' => $expressLine->id,
                        'type' => ExpressLinePrice::TYPE_FIRST_WEIGHT,
                        'start' => $firstWeight * 1000,
                        'end' => $firstWeight * 1000,
                    ],
                    [
                        'unit_weight' => null,
                    ]
                );

            foreach ($data['grades'] as $grade) {
                $newRules[] = $region->priceRules()
                    ->updateOrCreate(
                        [
                            'express_line_id' => $expressLine->id,
                            'type' => ExpressLinePrice::TYPE_GRADE_NEXT_WEIGHT,
                            'unit_weight' => $grade['unit_weight'] * 1000,
                        ],
                        [
                            'start' => 0,
                            'end' => 0,
                            'unit_weight' => $grade['unit_weight'] * 1000,
                            'scale_weight' => $grade['scale_weight'] * 1000,
                        ]
                    );
            }
        } elseif ($data['mode'] == ExpressLineModel::MODE_RANGE_FIRST_NEXT) {
            foreach ($data['grades'] as $grade) {
                $newRules[] = $region->priceRules()
                    ->updateOrCreate(
                        [
                            'express_line_id' => $expressLine->id,
                            'type' => ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT,
                            'start' => $grade['start'] * 1000,
                            'end' => $grade['end'] * 1000,
                        ],
                        [
                            'first_weight' => $grade['first_weight'] * 1000,
                            'unit_weight' => $grade['unit_weight'] * 1000,
                            'scale_weight' => $grade['scale_weight'] * 1000,
                        ]
                    );
            }
        }

        $dataInfo = ExpressLinePrice::query()->where('express_line_id', $expressLine->id)
            ->orderBy('end', 'desc')->first();
        // 对于最大重量
        // 只有多级续重直接指定
        // 如果没有直接指定 那么取价格阶梯里面的最大重量
        if (! empty($data['max_weight']) && $data['mode'] == ExpressLineModel::MODE_GRADE_NEXT) {
            $max_weight = $data['max_weight'] * 1000;
            if ($dataInfo && $dataInfo->end > $max_weight) {
                $max_weight = $dataInfo->end;
            }
            $expressLine->update(['max_weight' => $max_weight]);
        } else {
            if ($dataInfo) {
                $maxWeight = $dataInfo->end;
            } else {
                $maxWeight = collect($newRules)
                    ->sortByDesc(fn ($rule) => $rule->end)
                    ->first()
                    ->end;
            }
            $expressLine->update(['max_weight' => $maxWeight]);
        }

        //删除原来定义的的规则
        $region->priceRules()->whereKeyNot(collect($newRules)->pluck('id'))->delete();

        //更新价格表
        (new ExpressLinePriceService())->updateBase($expressLine, $regionId);

        return true;
    }

    /**
     * 更新单个分区的渠道价格
     */
    public function updateRegionPrices($regionId, $expressLineId, $data)
    {
        /** @var ExpressLineModel $expressLine */
        $expressLine = ExpressLineModel::query()->findOrFail($expressLineId);

        $region = ExpressLineRegion::query()->findOrFail($regionId);

        $mode = $expressLine->mode;

        $grades = $data['grades'];

        //价格标识 用于判断是否启用区域
        $setPrice = false;

        // 首重续重模式
        if ($mode === ExpressLineModel::MODE_1) {
            foreach ($grades as $key => $grade) {
                $start = $grade['start'] ?? 0;
                $end = $grade['end'] ?? 0;
                $price = $grade['unit_price'] ?? 0;

                //续重
                $type = ExpressLinePrice::TYPE_NEXT_WEIGHT;

                //首重
                if ($key === 0) {
                    $start = $end;//起始重量等于结束重量
                    $type = ExpressLinePrice::TYPE_FIRST_WEIGHT;
                }

                $region->prices()
                    ->where('type', $type)
                    ->where('start', $start * 1000)
                    ->where('end', $end * 1000)
                    ->update(['price' => $price * 100]);

                if ($price > 0) $setPrice = true;
            }
        }

        // 阶梯价格模式
        if ($mode === ExpressLineModel::MODE_2) {
            foreach ($grades as $grade) {
                $start = $grade['start'] ?? 0;
                $end = $grade['end'] ?? 0;
                $price = $grade['unit_price'] ?? 0;
                $basePrice = $grade['base_price'] ?? 0;

                $prices = $region->prices()
                    ->where('start', $start * 1000)
                    ->where('end', $end * 1000)
                    ->get();

                //更新价格
                $prices->each(function ($item) use ($price, $basePrice) {
                    //单价
                    if ($item->type === ExpressLinePrice::TYPE_GRADE_WEIGHT) {
                        $item->update(['price' => $price * 100]);
                    }

                    //操作费
                    if ($item->type === ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE) {
                        $item->update(['price' => $basePrice * 100]);
                    }
                });

                if ($price > 0) $setPrice = true;
            }

        }

        //阶梯首重续重模式
        if ($mode === ExpressLineModel::MODE_RANGE_FIRST_NEXT) {

        }

        //设置了价格则启用区域
        if ($setPrice) $region->update(['enabled' => 1]);

        return true;
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
                    throw new AccidentException('需要闭合且连续的重量区间:' . $range[$i] . '-' . $range[$i + 1], Code::OPERATE_FAIL);
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
     * @param int $eplId
     * @return bool
     * @throws Exception
     */
    public function export(int $eplId): bool
    {
        $expressLine = ExpressLineModel::query()->with('regions')->findOrFail($eplId);
        if ($expressLine->regions->isEmpty()) {
            throw new AccidentException('当前分区为空', Code::OPERATE_FAIL);
        }

        $mode = $expressLine->mode;
        if (empty($mode)) {
            throw new AccidentException('当前渠道设置的计费价格模式不正确', Code::OPERATE_FAIL);
        }

        $filename = $expressLine->name;

        //首重续重模式
        if ($mode === ExpressLineModel::MODE_1) {
            $filename .= '-首重续重-';
        }

        //阶梯价格模式
        if ($mode === ExpressLineModel::MODE_2) {
            $filename .= '-阶梯价格-';
        }

        $fileName = (remove_special_char($filename)) . Carbon::now()->format('Ymd') . '_' . Str::random(6) . '.xlsx';

        $url = secure_asset(Storage::disk('admin_public')->url($fileName));

        /** @var ExcelExport $export */
        $export = ExcelExport::query()->create([
            'type' => ExcelExport::TYPE_PRICE_TABLE,
            'name' => $fileName,
            'url' => $url,
            'status' => ExcelExport::STATUS_EXPORTING,
        ]);

        $params = [
            'express_line_id' => $eplId,
            'mode' => $mode,
        ];

        dispatch(new ExpressRegionExportJob($export, $params))->onQueue('export');

        return true;
    }

    /**
     * @param int $id
     * @param UploadedFile $file
     * @return bool
     * @throws Exception
     */
    public function importOld(int $id, UploadedFile $file)
    {
        $dataList = $this->parseData($file);

        DB::beginTransaction();
        try {

            //根据渠道ID查询渠道及分区
            $expressLine = ExpressLineModel::query()->with(['regions'])->select(['id', 'mode'])->findOrFail($id);

            $mode = $expressLine->mode ?? 0;//计费模式
            $regionNames = $expressLine->regions->pluck('id', 'name')->toArray();//分区名称

            $error = [];
            $regionData = [];
            $name = '';
            foreach ($dataList as $data) {
                $name = $data[0] ?: $name;//分区名称

                //合并单元格后分区只有第一行才有数据
                if ($data[0] && !isset($regionData[$name])) {
                    //参考时效 国家 邮编
                    [$days, $minimumChargeableWeight, $country, $postcode] = [$data[1], $data[2], $data[3], $data[4]];

                    if (!empty($minimumChargeableWeight) && !is_numeric($minimumChargeableWeight)) {
                        $error[] = $name . '：最低计费重只能为数字';
                        continue;
                    }

                    if (empty($country)) {
                        $error[] = $name . '：国家不能为空';
                        continue;
                    }

                    //校验国家
                    $country = str_replace(['、', '，', ','], '/', $country);
                    $countryArray = explode('/', $country);

                    //存在邮编时只支持单国家
                    if (!empty($postcode) && count($countryArray) > 1) {
                        $error[] = $name . '：邮编不为空时只支持单国家';
                        continue;
                    }

                    $areas = [];
                    foreach ($countryArray as $countryName) {
                        $countryId = Country::query()->where('cn_name', $countryName)->orWhere('en_name', $countryName)->value('id');
                        if (empty($countryId)) {
                            $error[] = $countryName . ': 国家未找到';
                            continue;
                        }

                        $areas[] = [
                            'country_id' => $countryId,
                        ];
                    }

                    if (empty($areas)) {
                        $error[] = $name . '：' . $country . '，请到基础配置里面添加国家地区';
                        continue;
                    }

                    //校验邮编
                    $postcodes = [];
                    if (!empty($postcode)) {
                        $postcode = str_replace(['、', '，'], ',', $postcode);
                        $postcodeArray = explode(',', $postcode);
                        foreach ($postcodeArray as $zip) {
                            $zipArray = explode('-', $zip);
                            $start = $zipArray[0];
                            $end =  $zipArray[1] ?? '';
                            $type = $end ? 1 : 2;

                            //兼容加拿大邮编数据
                            if ($areas[0]['country_id'] == ExpressLineRegionPostcodeAreaModel::CANADA_COUNTRY_ID) {
                                $start = strtoupper($start);
                                $end = strtoupper($end);

                                if (strlen($start) === 3) {
                                    $type = 1;
                                    if (strlen($end) === 0) {
                                        $end = $start . '9Z9';
                                    }

                                    $start = $start . '0A0';
                                }
                                if (strlen($end) === 3) {
                                    $type = 1;
                                    $end = $end . '9Z9';
                                }
                            } else {
                                if ($end && (!is_numeric($start) || !is_numeric($end))) {
                                    $error[] = $name . '：' .$zip . ': 邮编范围必须为数字';
                                    continue;
                                }

                                if ($end && ($start >= $end)) {
                                    $error[] = $name . '：' .$zip . ': 起始邮编必须小于结束邮编';
                                    continue;
                                }
                            }


                            $postcodes[] = [
                                "rule" => "邮编规则",
                                "start" => $start,
                                "end" => $end,
                                "type" => $type,
                            ];
                        }
                    }

                    $regionData[$name] = [
                        'name' => $name,
                        'reference_time' => (string)$days,
                        'minimum_chargeable_weight' => (float)$minimumChargeableWeight,
                        'type' => empty($postcode) ? 1 : 2,
                        'mode' => $mode,
                    ];

                    //单国家邮编
                    if (!empty($postcode)) {
                        $regionData[$name]['country_id'] = $areas[0]['country_id'];
                        $regionData[$name]['postcodes'] = $postcodes;
                    } else {
                        $regionData[$name]['areas'] = $areas;
                    }
                }
                $grade = [
                    "start" => 0,
                    "end" => 0,
                    "unit_weight" => 0,
                    "first_weight" => 0,
                    "scale_weight" => 0,
                ];
                //首重续重模式
                if ($mode === ExpressLineModel::MODE_1) {
                    //价格类型 起始重量 截止重量 进位制 单位续重
                    [$typeName, $start, $end, $scaleWeight, $unitWeight] = [$data[5], $data[6], $data[7], $data[8], $data[9]];

                    if (!in_array($typeName, ['首重', '续重'])) {
                        $error[] = $name . '：' . $typeName . ': 价格类型只能是【首重/续重】';
                        continue;
                    }

                    $type = $typeName === '首重' ? 0 : 1;
                    $grade['type'] = $type;
                    if ($type === 0) {
                        if (!is_numeric($end)) {
                            $error[] = $name . ': 首重的截止重量只能为数字类型';
                            continue;
                        }

                        $grade['end'] = $end;//首重重量
                    } else {
                        if (!empty($scaleWeight) && !is_numeric($scaleWeight)) {
                            $error[] = $name . ': 进位制只能为数字';
                            continue;
                        }

                        if (!is_numeric($start) || !is_numeric($end) || !is_numeric($unitWeight)) {
                            $error[] = $name . ': 重量不能为空且只能为数字';
                            continue;
                        }

                        $grade['start'] = $start;//起始重量
                        $grade['end'] = $end;//截止重量
                        $grade['unit_weight'] = $unitWeight;//单位续重
                        $grade['scale_weight'] = (float)$scaleWeight;//进位制
                    }

                }

                //阶梯价格模式
                if ($mode === ExpressLineModel::MODE_2) {
                    //起始重量 截止重量 进位制
                    [$start, $end, $scaleWeight,] = [$data[5], $data[6], $data[7]];

                    if (!empty($scaleWeight) && !is_numeric($scaleWeight)) {
                        $error[] = $name . ': 进位制只能为数字';
                        continue;
                    }

                    if (!is_numeric($start) || !is_numeric($end)) {
                        $error[] = $name . ': 重量不能为空且只能为数字';
                        continue;
                    }

                    $grade['start'] = $start;
                    $grade['end'] = $end;
                    $grade['scale_weight'] = (float)$scaleWeight;//进位制
                }

                //阶梯首重续重模式
                if ($mode === ExpressLineModel::MODE_RANGE_FIRST_NEXT) {
                    //价格类型 起始重量 截止重量 进位制 首重 单位续重
                    [$start, $end, $scaleWeight, $firstWeight, $unitWeight] = [$data[5], $data[6], $data[7], $data[8], $data[9]];

                    if (!empty($scaleWeight) && !is_numeric($scaleWeight)) {
                        $error[] = $name . ': 进位制只能为数字';
                        continue;
                    }

                    if (!is_numeric($start) || !is_numeric($end) || !is_numeric($firstWeight) || !is_numeric($unitWeight)) {
                        $error[] = $name . ': 重量不能为空且只能为数字';
                        continue;
                    }

                    $grade['type'] = 6;
                    $grade['start'] = $start;//起始重量
                    $grade['end'] = $end;//截止重量
                    $grade['first_weight'] = $firstWeight;//首重
                    $grade['unit_weight'] = $unitWeight;//单位续重
                    $grade['scale_weight'] = (float)$scaleWeight;//进位制
                }

                $regionData[$name]['grades'][] = $grade;
            }

            if ($error) {
                throw new AccidentException(implode('；', $error), Code::OPERATE_FAIL);
            }

            foreach ($regionData as $name => $data) {
                $regionId = $regionNames[$name] ?? 0;
                //存在分区名称则更新 否则新增
                if ($regionId) {
                    $this->update($regionId, $id, $data);
                } else {
                    $this->create($id, $data);
                }
            }

            DB::commit();
        } catch (\Throwable $throwable) {
            DB::rollBack();

            info('价格表导入失败', ['message' => $throwable->getMessage()]);

            throw new AccidentException('导入失败，请检查Excel数据格式:' . $throwable->getMessage(), Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * 模板导入
     * @param int $id
     * @param UploadedFile $file
     * @throws Exception
     */
    public function import(int $id, UploadedFile $file)
    {
        set_time_limit(0); // 0表示无限制

        $dataList = $this->parseData($file);

        DB::beginTransaction();
        try {
            //根据渠道ID查询渠道及分区
            $expressLine = ExpressLineModel::query()->with(['regions'])->select(['id', 'mode'])->findOrFail($id);

            $mode = $expressLine->mode ?? 0;//计费模式

            //根据国家-分区类型-分区名称查询id
            $regionIds = $expressLine->regions->flatMap(function ($region) {
                if ($region->type === ExpressLineRegion::TYPE_AREA) {
                    $countryName = $region->areas()->first()->country_name ?? '';
                } else {
                    $countryName = $region->country->cn_name ?? '';
                }

                $key = $countryName .'-'. $region->type . '-' . $region->name;
                return [$key => $region->id];
            })->toArray();//分区名称

            $error = [];
            $regionData = [];
            $regionMark = '';//分区标识 区分新增还是更新
            foreach ($dataList as $data) {
                $data = $data->toArray() ?? $data;

                //国家名称 区域
                [$countryName, $countryCode, $regionType] = [$data[0], $data[1], $data[4]];

                //分区类型
                $type = $regionType === '全国区域' ? ExpressLineRegion::TYPE_AREA : ExpressLineRegion::TYPE_POSTCODE;

                //合并单元格后分区只有第一行才有数据 这里循环到没有国家时取上一次保存的分区标识
                if (!empty($countryName) && !empty($type)) {
                    $regionMark = $countryName .'-'. $type . '-' . $regionType;
                }

                //处理分区数据
                if (!empty($countryName) && !isset($regionData[$regionMark])) {
                    if (empty($regionType)) {
                        $error[] = "{$countryName}：区域名称必填，不指定邮编时请填写“全国区域”";
                        continue;
                    }

                    //参考时效 最低计费重KG 邮编
                    [$days, $minimumChargeableWeight, $postcodeString] = [$data[2], $data[3], $data[5]];

                    if (!empty($minimumChargeableWeight) && !is_numeric($minimumChargeableWeight)) {
                        $error[] = "{$countryName}：最低计费重只能为数字";
                        continue;
                    }

                    //存在邮编时只支持单国家
                    if ($type === ExpressLineRegion::TYPE_POSTCODE) {
                        if (empty($postcodeString)) {
                            $error[] = "{$countryName}：区域不为“全国区域”时， 邮编必填";
                            continue;
                        }

                        if ($regionType === '全国区域') {
                            $error[] = "{$countryName}：邮编存在时，区域名称不能为空且不能重复";
                            continue;
                        }
                    }

                    $areas = [];
                    $countryId = Country::query()->where('code', strtolower($countryCode))->value('id');
                    if (empty($countryId)) {
                        $error[] = "{$countryName}【{$countryCode}】：国家查询失败，请到设置-基础资料-服务区域添加国家";
                        continue;
                    }

                    $areas[] = ['country_id' => $countryId,];

                    //校验邮编
                    $postcodes = [];
                    if ($type === ExpressLineRegion::TYPE_POSTCODE) {
                        $postcodeArray = explode(',', str_replace(['、', '，'], ',', $postcodeString));
                        foreach ($postcodeArray as $postcode) {
                            $code = explode('-', $postcode);
                            $start = $code[0];
                            $end =  $code[1] ?? '';
                            if (empty($start)) {
                                continue;
                            }

                            $postcodes[] = [
                                "rule" => "邮编规则",
                                "start" => $start,
                                "end" => $end,
                                "type" => $end ? 1 : 2,
                            ];
                        }
                    }

                    $regionData[$regionMark] = [
                        'reference_time' => (string)$days,
                        'minimum_chargeable_weight' => (float)$minimumChargeableWeight,
                        'type' => $type,
                        'mode' => $mode,
                    ];

                    //区域
                    if ($type === ExpressLineRegion::TYPE_AREA) {
                        $regionData[$regionMark]['areas'] = $areas;//全国区域
                    } else {
                        //部分区域
                        $regionData[$regionMark]['name'] = $regionType;
                        $regionData[$regionMark]['country_id'] = $areas[0]['country_id'];
                        $regionData[$regionMark]['postcodes'] = $postcodes;
                    }
                }

                if (empty($countryName)) {
                    $countryName = explode('-', $regionMark)[0] ?? '';
                }

                $grade = [
                    "start" => 0,//起始重量KG
                    "end" => 0,//结束重量KG
                    "unit_weight" => 0,//续重KG
                    "first_weight" => 0,//首重量KG
                    "scale_weight" => 0,//进位制KG
                    "unit_price" => 0,//单价
                    "base_price" => 0,//基价/操作费
                ];
                //首重续重模式
                if ($mode === ExpressLineModel::MODE_1) {
                    //起始重量KG 截止重量KG 进位制KG 价格类型 单位续重KG 单价￥
                    [$start, $end, $scaleWeight, $typeName, $unitWeight, $unitPrice] = array_slice($data, 6, 6);

                    if (!in_array($typeName, ['首重', '续重'])) {
                        $error[] = "{$countryName}: 价格类型只能是【首重/续重】";
                        continue;
                    }
                    $type = $typeName === '首重' ? 0 : 1;
                    $grade['type'] = $type;

                    if ($type === 0) {
                        if (!is_numeric($end)) {
                            $error[] = "{$countryName}: 首重的截止重量只能为数字类型";
                            continue;
                        }

                        $grade['end'] = (float)$end;//首重重量
                        $grade['unit_price'] = (float)$unitPrice;//首重单价
                    } else {
                        if (!empty($scaleWeight) && !is_numeric($scaleWeight)) {
                            $error[] = "{$countryName}: 进位制只能为数字";
                            continue;
                        }

                        if (!is_numeric($start) || !is_numeric($end) || !is_numeric($unitWeight)) {
                            $error[] = "{$countryName}: 续重的重量范围或者单位续重不能为空且只能为数字";
                            continue;
                        }

                        $grade['start'] = (float)$start;//起始重量KG
                        $grade['end'] = (float)$end;//截止重量KG
                        $grade['unit_weight'] = (float)$unitWeight;//单位续重KG
                        $grade['scale_weight'] = (float)$scaleWeight;//进位制KG
                        $grade['unit_price'] = (float)$unitPrice;//单价￥
                    }
                }

                //阶梯价格模式
                if ($mode === ExpressLineModel::MODE_2) {
                    //起始重量 截止重量 进位制
                    [$start, $end, $scaleWeight, $unitPrice, $basePrice] = array_slice($data, 6, 5);

                    if (!empty($start) && !is_numeric($end)) {
                        $error[] = "{$countryName}: 起始重量只能为数字";
                        continue;
                    }

                    if (!is_numeric($end)) {
                        $error[] = "{$countryName}: 截止重量不能为空且只能为数字";
                        continue;
                    }

                    if (!empty($scaleWeight) && !is_numeric($scaleWeight)) {
                        $error[] = "{$countryName}: 进位制只能为数字";
                        continue;
                    }

                    $grade['start'] = (float)$start;
                    $grade['end'] = (float)$end;
                    $grade['scale_weight'] = (float)$scaleWeight;//进位制
                    $grade['unit_price'] = (float)$unitPrice;//单价
                    $grade['base_price'] = (float)$basePrice;//操作费
                }

                $regionData[$regionMark]['grades'][] = $grade;
            }

            if ($error) {
                return array_values(array_unique($error));
            }

            $saveError = [];
            foreach ($regionData as $mark => $data) {
                $regionId = $regionIds[$mark] ?? 0;
                //存在分区名称则更新 否则新增
                try {
                    if ($regionId) {
                        $this->update($regionId, $id, $data);
                    } else {
                        $this->create($id, $data);
                    }
                } catch (Exception $e) {
                    $saveError[] = "{$mark}：{$e->getMessage()}";
                }
            }

            if ($saveError) return $saveError;

            DB::commit();
        } catch (\Throwable $throwable) {
            DB::rollBack();

            info('价格表导入失败', ['message' => $throwable->getMessage()]);

            throw new AccidentException('导入失败，请检查Excel数据格式：' . $throwable->getMessage(), Code::OPERATE_FAIL);
        }

        return true;
    }

    /**
     * @param UploadedFile $file
     * @return Collection
     * @throws AccidentException
     */
    protected function parseData(UploadedFile $file)
    {
        try {
            $config = ['path' => $file->getPath()];

            $excel = (new Excel($config))
                ->openFile($file->getFilename())
                ->openSheet();

            $items = collect([]);
            while (($row = $excel->nextRow()) !== null) {
                if (empty($row) || $this->areAllArrayElementsEmpty($row)) {
                    break;
                }

                $items->push(collect($row));
            }
            // 删除第一行说明性数据
            unset($items[0]);

            return $items;
        } catch (\Throwable $throwable) {
            throw new AccidentException('模板数据异常，请检查模板数据是否填写完整与正确', Code::OPERATE_FAIL);
        }
    }

    protected function rules()
    {
        return [
            'name' => 'required_if:type,2|nullable|string|max:32',
            'reference_time' => 'required|string',
            'type' => 'required|in:1,2',
            'country_id' => 'required_if:type,2|integer',
            'delivery_min_days' => 'sometimes|nullable|integer',
            'delivery_max_days' => 'sometimes|nullable|integer',
            'postcodes' => 'required_if:type,2|array',
            'postcodes.*.type' => 'required|in:1,2,3',
            // 'postcodes.*.start' => 'required|regex:/\d+/',
            'postcodes.*.start' => 'required',
            // 'postcodes.*.end' => 'sometimes|nullable|regex:/\d+/',
            'postcodes.*.end' => 'sometimes|nullable',
            'areas' => 'required_if:type,1|array',
            'areas.*.country_id' => 'required',
            'areas.*.area_id' => 'sometimes|nullable',
            'areas.*.sub_area_id' => 'sometimes|nullable',
            'index' => 'sometimes|nullable|integer',
            'minimum_chargeable_weight' => 'sometimes|nullable|numeric',

            'mode' => 'required|in:1,2,3,4,5',
            'grades' => 'required|array',
            'grades.*.start' => 'required_unless:mode,4',
            'grades.*.end' => 'required_unless:mode,4',
            'grades.*.unit_weight' => 'sometimes|nullable',
            'grades.*.first_weight' => 'required_if:mode,5',
            'grades.*.scale_weight' => 'sometimes|nullable|numeric',
            'grades.*.unit_price' => 'sometimes|nullable|numeric',//单价
            'grades.*.base_price' => 'sometimes|nullable|numeric',//基价/操作费
        ];
    }

    protected function translateRules()
    {
        return array_merge(
            parent::translateRules(),
            [
                'name' => 'required|string|max:32',
                'reference_time' => 'required|string|max:32',
            ]
        );
    }

    protected function areAllArrayElementsEmpty($array): bool
    {
        $array = array_filter(array_unique($array));
        if (empty($array)) {
            return true; // 所有元素都为空，返回true
        }
        return false; // 发现非空元素，返回false
    }

}
