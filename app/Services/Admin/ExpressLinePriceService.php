<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:40
 */

namespace App\Services\Admin;

use App\Jobs\Export\RegionPriceExport;
use App\Jobs\ImportExpressLinePrice;
use App\Lib\Code;
use App\Models\ExcelExport;
use App\Models\ExpressLineModel;
use App\Models\ExpressLinePrice;
use App\Models\ExpressLineRegion;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Vtiful\Kernel\Excel;
use App\Exceptions\AccidentException;

/**
 * 快递线路价格配置
 *
 * Class ExpressLinePriceService
 * @package App\Services\Admin
 */
class ExpressLinePriceService extends BaseService
{
    use HasNameUniqueValidation,
        HasStatusSetting;

    public function __construct()
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = new ExpressLineModel();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }


    public function info($id)
    {
        return ExpressLineRegion::query()
            ->with(['prices' => fn ($q) => $q->orderBy('start'), 'areas'])
            ->where('express_line_id', $id)
            ->get();
    }

    /**
     * 初始化分区价格数据
     *
     * @param ExpressLineModel $expressLine
     * @param int $regionId
     * @return bool
     */
    public function initBase(ExpressLineModel $expressLine, int $regionId = 0): bool
    {
        DB::transaction(function () use ($expressLine, $regionId) {
            if ($regionId) {
                $expressLine->load([
                    'priceRules' => function ($query) use ($regionId) {
                        $query->where('region_id', $regionId);
                    },
                    'regions' => function ($query) use ($regionId) {
                        $query->where('id', $regionId);
                    },
                ]);
            } else {
                $expressLine->load(['priceRules', 'regions']);
            }

            if ($expressLine->priceRules->isEmpty()) {
                return true;
            }

            $prices = [];
            $rules = $expressLine->priceRules;
            $regions = $expressLine->regions;

            //首重续重模式的初始化
            if ($expressLine->mode === ExpressLineModel::MODE_1) {
                $prices = $this->initFirstAndNextWeightPrices($regions, $rules);
            }
            //单位阶梯价格的初始化
            if ($expressLine->mode === ExpressLineModel::MODE_2) {
                $prices = $this->initTieredPrices($regions, $rules);
            }
            //首重加阶梯价格的初始化
            if ($expressLine->mode === ExpressLineModel::MODE_MIX) {
                $prices = $this->initMixesPrices($regions, $rules);
            }
            //多级续重价格的初始化
            if ($expressLine->mode === ExpressLineModel::MODE_GRADE_NEXT) {
                $prices = $this->initGradeNextPrices($regions, $rules);
            }
            //阶梯范围首重续重价格的初始化
            if ($expressLine->mode === ExpressLineModel::MODE_RANGE_FIRST_NEXT) {
                $prices = $this->initRangeFirstNextPrices($regions, $rules);
            }

            $expressLine->prices()->insert($prices);
        });

        return true;
    }

    /**
     * @param ExpressLineModel $expressLine
     * @param int $regionId
     * @return bool
     */
    public function updateBase(ExpressLineModel $expressLine, int $regionId = 0)
    {
        return DB::transaction(function () use ($expressLine, $regionId) {
            if ($regionId) {
                $expressLine->load([
                    'priceRules' => function ($query) use ($regionId) {
                        $query->where('region_id', $regionId);
                    },
                    'regions' => function ($query) use ($regionId) {
                        $query->where('id', $regionId);
                    },
                ]);
            } else {
                $expressLine->load(['priceRules', 'regions']);
            }

            if ($expressLine->priceRules->isEmpty()) {
                return true;
            }

            $rules = $expressLine->priceRules;
            $regions = $expressLine->regions;

            //首重续重模式的更新
            if ($expressLine->mode === ExpressLineModel::MODE_1) {
                return $this->updateFirstAndNextWeightPrices($regions, $rules);
            }
            //单位阶梯价格的更新
            if ($expressLine->mode === ExpressLineModel::MODE_2) {
                return $this->updateTieredPrices($regions, $rules);
            }
            //单位价格加阶梯附加价格的初始化
            if ($expressLine->mode === ExpressLineModel::MODE_MIX) {
                return $this->updateMixesPrices($regions, $rules);
            }
            //多级续重价格的初始化
            if ($expressLine->mode === ExpressLineModel::MODE_GRADE_NEXT) {
                return $this->updateGradeNextPrices($regions, $rules);
            }
            //阶梯范围首重续重价格的更新
            if ($expressLine->mode === ExpressLineModel::MODE_RANGE_FIRST_NEXT) {
                return $this->updateRangeFirstNextPrices($regions, $rules);
            }

            return true;
        });
    }

    /**
     * @param ExpressLineModel $expressLine
     * @return bool
     */
    public function updateBase0625(ExpressLineModel $expressLine)
    {
        return DB::transaction(function () use ($expressLine) {
            $expressLine->load(['priceRules', 'regions']);

            $rules = $expressLine->priceRules;
            $regions = $expressLine->regions;
            //首重续重模式的更新
            if ($expressLine->mode === ExpressLineModel::MODE_1) {
                return $this->updateFirstAndNextWeightPrices($regions, $rules);
            }
            //单位阶梯价格的更新
            if ($expressLine->mode === ExpressLineModel::MODE_2) {
                return $this->updateTieredPrices($regions, $rules);
            }
            //单位价格加阶梯附加价格的初始化
            if ($expressLine->mode === ExpressLineModel::MODE_MIX) {
                return $this->updateMixesPrices($regions, $rules);
            }
            //多级续重价格的初始化
            if ($expressLine->mode === ExpressLineModel::MODE_GRADE_NEXT) {
                return $this->updateGradeNextPrices($regions, $rules);
            }
            //阶梯范围首重续重价格的更新
            if ($expressLine->mode === ExpressLineModel::MODE_RANGE_FIRST_NEXT) {
                return $this->updateRangeFirstNextPrices($regions, $rules);
            }

            return true;
        });
    }

    /**
     * @param ExpressLineRegion $region
     * @return mixed
     */
    public function initByRegion(ExpressLineRegion $region): mixed
    {
        return DB::transaction(function () use ($region) {
            $expressLine = $region->expressLine;
            $expressLine->load(['priceRules']);

            $prices = [];
            $rules = $expressLine->priceRules;
            //首重续重模式的初始化
            if ($expressLine->mode === ExpressLineModel::MODE_1) {
                $rules->each(function ($rule) use ($region, &$prices) {
                    //设置一个首重价格
                    if ($rule['type'] === ExpressLinePrice::TYPE_FIRST_WEIGHT) {
                        $prices[] = [
                            'express_line_id' => $rule['express_line_id'],
                            'region_id' => $region->id,
                            'type' => $rule['type'],
                            'start' => $rule['start'],
                            'end' => $rule['start'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    } else {
                        //设置多个阶梯续重价格
                        $start = $rule['start'];
                        $end = $rule['end'];
                        $unit = $rule['unit_weight'];

                        $prices[] = [
                            'express_line_id' => $region->express_line_id,
                            'region_id' => $region->id,
                            'type' => 1,
                            'start' => $start,
                            'end' => $end,
                            'unit_weight' => $unit,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                });
            }
            //单位阶梯价格的初始化
            if ($expressLine->mode === ExpressLineModel::MODE_2) {
                $prices = array_merge($prices, ...$rules->map(function ($item) use ($region) {
                    return [
                        [
                            'express_line_id' => $item['express_line_id'],
                            'region_id' => $region->id,
                            'type' => 2,
                            'start' => $item['start'],
                            'end' => $item['end'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ], [
                            'express_line_id' => $item['express_line_id'],
                            'region_id' => $region->id,
                            'type' => ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE,
                            'start' => $item['start'],
                            'end' => $item['end'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    ];
                })->all());
            }
            //首重加阶梯价格的初始化
            if ($expressLine->mode === ExpressLineModel::MODE_MIX) {
                $rules->each(function ($item) use ($region, &$prices) {
                    if ($item['type'] === ExpressLinePrice::TYPE_UNIT_WEIGHT) {
                        $prices[] = [
                            'express_line_id' => $item['express_line_id'],
                            'region_id' => $region->id,
                            'type' => $item['type'],
                            'start' => 0,
                            'end' => 0,
                            'unit_weight' => 1000,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    } else {
                        $prices[] = [
                            'express_line_id' => $item['express_line_id'],
                            'region_id' => $region->id,
                            'type' => $item['type'],
                            'start' => $item['start'],
                            'end' => $item['end'],
                            'unit_weight' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                });
            }
            //多级续重价格的初始化
            if ($expressLine->mode === ExpressLineModel::MODE_GRADE_NEXT) {
                $rules->each(function ($item) use ($region, &$prices) {
                    if ($item['type'] === ExpressLinePrice::TYPE_FIRST_WEIGHT) {
                        $prices[] = [
                            'express_line_id' => $item['express_line_id'],
                            'region_id' => $region->id,
                            'type' => $item['type'],
                            'start' => $item['start'],
                            'end' => $item['start'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    } else {
                        $prices[] = [
                            'express_line_id' => $item['express_line_id'],
                            'region_id' => $region->id,
                            'type' => $item['type'],
                            'start' => $item['start'],
                            'end' => $item['start'],
                            'unit_weight' => $item['unit_weight'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                });
            }
            //多级续重价格的初始化
            if ($expressLine->mode === ExpressLineModel::MODE_RANGE_FIRST_NEXT) {
                $prices = array_merge($prices, ...$rules->map(function ($item) use ($region) {
                    return [
                        [
                            'express_line_id' => $item['express_line_id'],
                            'region_id' => $region->id,
                            'type' => ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT,
                            'start' => $item['start'],
                            'end' => $item['end'],
                            'first_weight' => $item['first_weight'],
                            'unit_weight' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                        [
                            'express_line_id' => $item['express_line_id'],
                            'region_id' => $region->id,
                            'type' => ExpressLinePrice::TYPE_RANGE_NEXT_WEIGHT,
                            'start' => $item['start'],
                            'end' => $item['end'],
                            'first_weight' => null,
                            'unit_weight' => $item['unit_weight'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    ];
                })->all());
            }

            $expressLine->prices()->createMany($prices);

            return true;
        });
    }

    /**
     * 更新区域价格数据
     *
     * @param  int  $id
     * @param  array  $data
     * @return bool
     * @throws ValidationException
     */
    public function update(int $id, array $data): bool
    {unset($data['uuid']);
        $data = validator($data, $this->rules())->validate();

        return DB::transaction(function () use ($id, $data) {
            collect($data)->each(function ($item) use ($id) {
                //为每个区域下的每个价格档位设置价格
                collect($item['prices'])->each(function ($item) {

                    ExpressLinePrice::query()
                        ->whereKey($item['id'])
                        ->update(['price' => $item['price'] * 100]);

                    return true;
                });
            });

            return true;
        });
    }

    public function delete(array $id): bool
    {
        return parent::delete($id);
    }

    /**
     * 首重续重模式的更新
     *
     * @param Collection $regions
     * @param Collection $rules
     * @return bool
     */
    protected function updateFirstAndNextWeightPrices(Collection $regions, Collection $rules): bool
    {
        /** @var ExpressLineRegion $region */
        foreach ($regions as $region) {
            $newPrices = [];
            $rules->each(function ($item) use ($region, &$prices, &$newPrices) {
                $start = $item['start'];
                $end = $item['end'];
                $unit = $item['unit_weight'];

                //设置一个首重价格
                if ($item['type'] === ExpressLinePrice::TYPE_FIRST_WEIGHT) {
                    $price = $region->prices()
                        ->where('type', ExpressLinePrice::TYPE_FIRST_WEIGHT)
                        ->where([
                            ['start', '=', $end],
                            ['end', '=', $end],
                        ])->first();
                    if ($price) {
                        $price->update([
                            'start' => $end,
                            'end' => $end,
                        ]);
                        $newPrices[] = $price->getKey();
                    } else {
                        $newPrices[] = $region->prices()->create([
                            'express_line_id' => $region->express_line_id,
                            'region_id' => $region->id,
                            'type' => ExpressLinePrice::TYPE_FIRST_WEIGHT,
                            'start' => $end,
                            'end' => $end,
                            'unit_weight' => $unit,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])->getKey();
                    }
                } else {
                    //设置多个阶梯附加价格

                    $price = $region->prices()
                        ->where('type', ExpressLinePrice::TYPE_NEXT_WEIGHT)
                        ->where([
                            ['start', '=', $start],
                            ['end', '=', $end],
                        ])->first();

                    if ($price) {
                        $price->update(['unit_weight' => $unit]);
                        $newPrices[] = $price->getKey();
                    } else {
                        $newPrices[] = $region->prices()->create([
                            'express_line_id' => $region->express_line_id,
                            'region_id' => $region->id,
                            'type' => ExpressLinePrice::TYPE_NEXT_WEIGHT,
                            'start' => $start,
                            'end' => $end,
                            'unit_weight' => $unit,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])->getKey();
                    }
                }
            });

            //删除旧的价格
            $region->prices()
                // ->whereNotIn('type', [ExpressLinePrice::TYPE_FIRST_WEIGHT])
                ->whereKeyNot($newPrices)
                ->delete();
        }

        return true;
    }

    /**
     * @param Collection $regions
     * @param Collection $rules
     * @return bool
     */
    protected function updateTieredPrices(Collection $regions, Collection $rules): bool
    {
        /** @var ExpressLineRegion $region */
        foreach ($regions as $region) {
            $newPrices = [];
            $rules->each(function ($item) use ($region, &$newPrices) {
                $newPrices[] = $region->prices()
                    ->updateOrCreate(
                        [
                            'type' => ExpressLinePrice::TYPE_GRADE_WEIGHT,
                            'express_line_id' => $region->express_line_id,
                            'start' => $item['start'],
                            'end' => $item['end'],
                        ],
                        [
                            'unit_weight' => null,
                        ]
                    )->getKey();
                $newPrices[] = $region->prices()
                    ->updateOrCreate(
                        [
                            'type' => ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE,
                            'express_line_id' => $region->express_line_id,
                            'start' => $item['start'],
                            'end' => $item['end'],
                        ],
                        [
                            'unit_weight' => null,
                        ]
                    )->getKey();
            });
            //删除旧的价格 只有一种
            $region->prices()
                ->whereKeyNot($newPrices)
                ->delete();
        }

        return true;
    }

    /**
     * @param Collection $regions
     * @param Collection $rules
     * @return bool
     */
    protected function updateMixesPrices(Collection $regions, Collection $rules): bool
    {
        /** @var ExpressLineRegion $region */
        foreach ($regions as $region) {
            $newPrices = [];
            $rules->each(function ($item) use ($region, &$prices, &$newPrices) {
                //设置一个首重价格
                if ($item['type'] === ExpressLinePrice::TYPE_UNIT_WEIGHT) {
                    $region->prices()
                        ->where('type', ExpressLinePrice::TYPE_UNIT_WEIGHT)
                        ->update([
                            'start' => 0,
                            'end' => 0,
                        ]);
                } else {
                    //设置多个阶梯附加价格
                    $start = $item['start'];
                    $end = $item['end'];

                    $price = $region->prices()
                        ->where('type', ExpressLinePrice::TYPE_GRADE_WEIGHT_APPEND)
                        ->where([
                            ['start', '=', $start],
                            ['end', '=', $end],
                        ])->first();

                    if ($price) {
                        $newPrices[] = $price->getKey();
                    } else {
                        $newPrices[] = $region->prices()->create([
                            'express_line_id' => $region->express_line_id,
                            'region_id' => $region->id,
                            'type' => ExpressLinePrice::TYPE_GRADE_WEIGHT_APPEND,
                            'start' => $start,
                            'end' => $end,
                            'unit_weight' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])->getKey();
                    }
                }
            });
            //删除旧的价格
            $region->prices()
                ->whereNotIn('type', [ExpressLinePrice::TYPE_UNIT_WEIGHT])
                ->whereKeyNot($newPrices)
                ->delete();
        }

        return true;
    }

    /**
     * @param Collection $regions
     * @param Collection $rules
     * @return bool
     */
    protected function updateGradeNextPrices(Collection $regions, Collection $rules): bool
    {
        /** @var ExpressLineRegion $region */
        foreach ($regions as $region) {
            $newPrices = [];
            $rules->each(function ($item) use ($region, &$prices, &$newPrices) {
                //设置一个首重价格
                if ($item['type'] === ExpressLinePrice::TYPE_FIRST_WEIGHT) {
                    $region->prices()
                        ->where('type', ExpressLinePrice::TYPE_FIRST_WEIGHT)
                        ->update([
                            'start' => $item['start'],
                            'end' => $item['start'],
                        ]);
                } else {
                    //设置多个阶梯附加价格
                    $start = 0;
                    $end = 0;
                    $unit = $item['unit_weight'];

                    $price = $region->prices()
                        ->where('type', ExpressLinePrice::TYPE_GRADE_NEXT_WEIGHT)
                        ->where([
                            ['unit_weight', '=', $unit],
                        ])->first();

                    if ($price) {
                        $price->update(['unit_weight' => $unit]);
                        $newPrices[] = $price->getKey();
                    } else {
                        $newPrices[] = $region->prices()->create([
                            'express_line_id' => $region->express_line_id,
                            'region_id' => $region->id,
                            'type' => ExpressLinePrice::TYPE_GRADE_NEXT_WEIGHT,
                            'start' => $start,
                            'end' => $end,
                            'unit_weight' => $unit,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])->getKey();
                    }
                }
            });

            //删除旧的价格
            $region->prices()
                ->whereNotIn('type', [ExpressLinePrice::TYPE_FIRST_WEIGHT])
                ->whereKeyNot($newPrices)
                ->delete();
        }

        return true;
    }

    /**
     * @param Collection $regions
     * @param Collection $rules
     * @return bool
     */
    protected function updateRangeFirstNextPrices(Collection $regions, Collection $rules): bool
    {
        /** @var ExpressLineRegion $region */
        foreach ($regions as $region) {
            $newPrices = [];
            $rules->each(function ($item) use ($region, &$newPrices) {
                $newPrices[] = $region->prices()
                    ->updateOrCreate(
                        [
                            'type' => ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT,
                            'express_line_id' => $region->express_line_id,
                            'start' => $item['start'],
                            'end' => $item['end'],
                        ],
                        [
                            'first_weight' => $item['first_weight'],
                            'unit_weight' => null,
                        ]
                    )->getKey();

                $newPrices[] = $region->prices()
                    ->updateOrCreate(
                        [
                            'type' => ExpressLinePrice::TYPE_RANGE_NEXT_WEIGHT,
                            'express_line_id' => $region->express_line_id,
                            'start' => $item['start'],
                            'end' => $item['end'],
                        ],
                        [
                            'first_weight' => null,
                            'unit_weight' => $item['unit_weight'],
                        ]
                    )->getKey();
            });
            //删除旧的价格 只有一种
            $region->prices()
                ->whereKeyNot($newPrices)
                ->delete();
        }

        return true;
    }

    /**
     * 首重续重模式的初始化
     *
     * @param Collection $regions
     * @param Collection $rules
     * @return array
     */
    protected function initFirstAndNextWeightPrices(Collection $regions, Collection $rules): array
    {
        $prices = [];

        foreach ($regions as $region) {
            $rules->each(function ($item) use ($region, &$prices) {
                //设置一个首重价格
                if ($item['type'] === ExpressLinePrice::TYPE_FIRST_WEIGHT) {
                    $prices[] = [
                        'express_line_id' => $item['express_line_id'],
                        'region_id' => $region->id,
                        'type' => $item['type'],
                        'start' => $item['start'],
                        'end' => $item['end'],
                        'unit_weight' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                } else {
                    //设置多个阶梯续重价格
                    $start = $item['start'];
                    $end = $item['end'];
                    $unit = $item['unit_weight'];

                    $prices[] = [
                        'express_line_id' => $region->express_line_id,
                        'region_id' => $region->id,
                        'type' => 1,
                        'start' => $start,
                        'end' => $end,
                        'unit_weight' => $unit,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            });
        }

        return $prices;
    }

    /**
     * @param Collection $regions
     * @param Collection $rules
     * @return array
     */
    protected function initTieredPrices(Collection $regions, Collection $rules): array
    {
        $prices = [];

        foreach ($regions as $region) {
            $prices = array_merge($prices, ...$rules->map(function ($item) use ($region) {
                return [
                    [
                        'express_line_id' => $item['express_line_id'],
                        'region_id' => $region->id,
                        'type' => ExpressLinePrice::TYPE_GRADE_WEIGHT,
                        'start' => $item['start'],
                        'end' => $item['end'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'express_line_id' => $item['express_line_id'],
                        'region_id' => $region->id,
                        'type' => ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE,
                        'start' => $item['start'],
                        'end' => $item['end'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                ];
            })->all());
        }

        return $prices;
    }

    /**
     * @param Collection $regions
     * @param Collection $rules
     * @return array
     */
    protected function initRangeFirstNextPrices(Collection $regions, Collection $rules): array
    {
        $prices = [];

        foreach ($regions as $region) {
            $prices = array_merge($prices, ...$rules->map(function ($item) use ($region) {
                return [
                    [
                        'express_line_id' => $item['express_line_id'],
                        'region_id' => $region->id,
                        'type' => ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT,
                        'start' => $item['start'],
                        'end' => $item['end'],
                        'first_weight' => $item['first_weight'],
                        'unit_weight' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], [
                        'express_line_id' => $item['express_line_id'],
                        'region_id' => $region->id,
                        'type' => ExpressLinePrice::TYPE_RANGE_NEXT_WEIGHT,
                        'start' => $item['start'],
                        'end' => $item['end'],
                        'first_weight' => null,
                        'unit_weight' => $item['unit_weight'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                ];
            })->all());
        }

        return $prices;
    }

    /**
     * @param Collection $regions
     * @param Collection $rules
     * @return array
     */
    protected function initMixesPrices(Collection $regions, Collection $rules): array
    {
        $prices = [];

        foreach ($regions as $region) {
            $rules->each(function ($item) use ($region, &$prices) {
                if ($item['type'] === ExpressLinePrice::TYPE_UNIT_WEIGHT) {
                    $prices[] = [
                        'express_line_id' => $item['express_line_id'],
                        'region_id' => $region->id,
                        'type' => $item['type'],
                        'start' => 0,
                        'end' => 0,
                        'unit_weight' => 1000,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                } else {
                    $prices[] = [
                        'express_line_id' => $item['express_line_id'],
                        'region_id' => $region->id,
                        'type' => $item['type'],
                        'start' => $item['start'],
                        'end' => $item['end'],
                        'unit_weight' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            });
        }

        return $prices;
    }

    /**
     * @param Collection $regions
     * @param Collection $rules
     * @return array
     */
    protected function initGradeNextPrices(Collection $regions, Collection $rules): array
    {
        $prices = [];

        foreach ($regions as $region) {
            $rules->each(function ($item) use ($region, &$prices) {
                if ($item['type'] === ExpressLinePrice::TYPE_FIRST_WEIGHT) {
                    $prices[] = [
                        'express_line_id' => $item['express_line_id'],
                        'region_id' => $region->id,
                        'type' => $item['type'],
                        'start' => $item['start'],
                        'end' => $item['start'],
                        'unit_weight' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                } else {
                    $prices[] = [
                        'express_line_id' => $item['express_line_id'],
                        'region_id' => $region->id,
                        'type' => $item['type'],
                        'start' => $item['start'],
                        'end' => $item['start'],
                        'unit_weight' => $item['unit_weight'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            });
        }

        return $prices;
    }

    /**
     * @param int $eplId
     * @return bool
     * @throws Exception
     */
    public function export(int $eplId): bool
    {
        $items = ExpressLineRegion::query()
            ->with(['prices', 'areas', 'expressLine'])
            ->where('express_line_id', $eplId)
            ->get();

        if ($items->isEmpty()) {
            throw new AccidentException('当前分区为空', Code::OPERATE_FAIL);
        }

        $mode = $items[0]->expressLine->mode ?? 0;
        if (empty($mode)) {
            throw new AccidentException('当前渠道设置的计费价格模式不正确', Code::OPERATE_FAIL);
        }

        $header = $appends = [];

        //首重续重模式
        if ($mode === ExpressLineModel::MODE_1) {
            $header[] = [
                '分区名称',
                '价格类型',
                '起始重量KG',
                '截止重量KG',
                '单位续重KG',
                '单价￥',
            ];

            $items->each(function ($item) use (&$appends) {
                $regionName = $item->name ?? '';
                $regionPriceList = [];
                $item->prices->each(function ($price) use ($regionName, &$regionPriceList) {
                    $start = $price->start / 1000;
                    $end = $price->end / 1000;
                    $unitWeight = $price->unit_weight / 1000;
                    $amount = ($price->price ?? 0) / 100;
                    $type = '续重';
                    //单价
                    if ($price->type === ExpressLinePrice::TYPE_FIRST_WEIGHT) {
                        $start = 0;
                        $type = '首重';
                    }

                    $regionPrice = [
                        'region_name' => $regionName,//分区名称
                        'type' => $type,//价格类型
                        'start' => $start,//起始重量KG
                        'end' => $end,//截止重量KG
                        'unit_weight' => $unitWeight,//单位续重KG
                        'price' => $amount,//单价￥
                    ];

                    $regionPriceList[] = array_values($regionPrice);
                });

                //根据起始重量排序
                $regionPriceList = collect($regionPriceList)->sortBy(2)->values()->all();

                $appends = array_merge($appends, $regionPriceList);
            });
        }

        //阶梯价格模式
        if ($mode === ExpressLineModel::MODE_2) {
            $header[] = [
                '分区名称',
                '起始重量KG',
                '截止重量KG',
                '单价￥',
                '挂号费￥',
            ];

            $items->each(function ($item) use (&$appends) {
                $regionName = $item->name ?? '';
                $regionPriceList = [];
                $item->prices->each(function ($price) use ($regionName, &$regionPriceList) {
                    $start = $price->start / 1000;
                    $end = $price->end / 1000;
                    $amount = ($price->price ?? 0) / 100;
                    //单价
                    if ($price->type === ExpressLinePrice::TYPE_GRADE_WEIGHT) {
                        $regionPriceList[] = [
                            'region_name' => $regionName,//分区名称
                            'start' => $start,//起始重量KG
                            'end' => $end,//截止重量KG
                            'price' => $amount,//单价￥
                        ];
                    }
                });

                //循环单价, 根据重量区间匹配操作费
                collect($regionPriceList)->each(function ($regionPrice, $key) use (&$regionPriceList, $item) {

                    $item->prices->each(function ($price) use (&$regionPrice) {
                        $start = $price->start / 1000;
                        $end = $price->end / 1000;
                        $amount = ($price->price ?? 0) / 100;

                        //操作费
                        if ($price->type === ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE && $start === $regionPrice['start'] && $end === $regionPrice['end']) {
                            $regionPrice['base_price'] = $amount;
                            return false;
                        }
                    });

                    $regionPriceList[$key] = array_values($regionPrice);
                });

                //根据起始重量排序
                $regionPriceList = collect($regionPriceList)->sortBy(1)->values()->all();

                $appends = array_merge($appends, $regionPriceList);
            });
        }

        //阶梯首重续重
        if ($mode === ExpressLineModel::MODE_RANGE_FIRST_NEXT) {
            $header[] = [
                '分区名称',
                '起始重量KG',
                '截止重量KG',
                '首重重量KG',
                '首重价格￥',
                '单位续重重量KG',
                '续重单价￥',
            ];

            $items->each(function ($item) use (&$appends) {
                $regionName = $item->name ?? '';
                $regionPriceList = [];
                $item->prices->each(function ($price) use ($regionName, &$regionPriceList) {
                    $start = $price->start / 1000;
                    $end = $price->end / 1000;
                    $unitWeight = $price->unit_weight / 1000;
                    $amount = ($price->price ?? 0) / 100;
                    //续重单价
                    if ($price->type === ExpressLinePrice::TYPE_RANGE_NEXT_WEIGHT) {
                        $regionPriceList[] = [
                            'region_name' => $regionName,//分区名称
                            'start' => $start,//起始重量KG
                            'end' => $end,//截止重量KG
                            'first_weight' => 0,//首重重量KG
                            'base_price' => 0,//首重价格￥
                            'unit_weight' => $unitWeight,//单位续重重量KG
                            'price' => $amount,//单价￥
                        ];
                    }
                });

                //循环单价, 根据重量区间匹配首重价格
                collect($regionPriceList)->each(function ($regionPrice, $key) use (&$regionPriceList, $item) {

                    $item->prices->each(function ($price) use (&$regionPrice) {
                        $start = $price->start / 1000;
                        $end = $price->end / 1000;
                        $firstWeight = $price->first_weight / 1000;
                        $amount = ($price->price ?? 0) / 100;

                        //首重价格
                        if ($price->type === ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT && $start === $regionPrice['start'] && $end === $regionPrice['end']) {
                            $regionPrice['first_weight'] = $firstWeight;
                            $regionPrice['base_price'] = $amount;
                            return false;
                        }
                    });

                    $regionPriceList[$key] = array_values($regionPrice);
                });

                //根据起始重量排序
                $regionPriceList = collect($regionPriceList)->sortBy(1)->values()->all();

                $appends = array_merge($appends, $regionPriceList);
            });
        }

        $prices = array_merge($header, $appends);

        $epl = ExpressLineModel::query()->find($eplId);

        $fileName = (remove_special_char($epl->name)) .'_prices_' . Carbon::now()->format('Ymd') . '_' . Str::random(6) . '.xlsx';

        $filePath = "/excel/prices/$fileName";

        $url = secure_asset(Storage::disk('admin_public')->url($filePath));

        /** @var ExcelExport $export */
        $export = ExcelExport::query()->create([
            'type' => ExcelExport::TYPE_PRICE_TABLE,
            'name' => $fileName,
            'url' => $url,
            'status' => ExcelExport::STATUS_EXPORTING,
        ]);

        dispatch(new RegionPriceExport($export, $prices));

        return true;
    }

    /**
     * @param int $eplId
     * @return bool
     * @throws Exception
     */
    public function export0703(int $eplId): bool
    {
        $items = ExpressLineRegion::query()
            ->with(['prices', 'areas', 'expressLine'])
            ->where('express_line_id', $eplId)
            ->get();

        if ($items->isEmpty()) {
            throw new AccidentException('当前分区为空', Code::OPERATE_FAIL);
        }

        if ($merge = ($items[0]->expressLine->mode === ExpressLineModel::MODE_2)) {
            $names = $items->pluck('name')->flatten()->values();

            $s = [];
            do {
                array_push($s, '基价', '单价');
            } while ((count($s) / 2) < $names->count());

            $first = $names
                ->push($names->all())
                ->flatten()
                ->sortBy(fn($i) => $i)
                ->prepend(['#', '', '', ''])
                ->flatten()
                ->all();

            $second = collect($s)->prepend(['#', '重量范围', '首重/单位重量', '价格类型'])->flatten()->all();

            $line = $items->first()?->prices?->count();

            $prices[] = $first;
            $prices[] = $second;
            $appends = [];
            for ($i = 0; $i < $line; $i+= 2) {
                $range = (function ($items, $i) {
                    $price = $items->first()?->prices->skip($i)->first();
                    $start = $price->start / 1000;

                    if ($price->start === $price->end) {
                        return "[$start]";
                    } else {
                        $end = $price->end / 1000;
                        return "[$start, $end)";
                    }
                }) ($items, $i);
                // 这个值是 单位重量或者首重
                $unitWeight = (function ($items, $i) {
                    $price = $items->first()?->prices->skip($i)->first();
                    return $price->unit_weight
                        ? $price->unit_weight / 1000
                        : ($price->first_weight ? $price->first_weight / 1000 : 0);
                }) ($items, $i);

                $type = (function ($items, $i) {
                    $price = $items->first()?->prices->skip($i)->first();

                    return match ($price->type) {
                        0 => '首重',
                        1 => '续重',
                        2, ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE => '阶梯价格',
                        3 => '单位价格',
                        4 => '阶梯附加价格',
                        5 => '多级续重价格',
                        ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT => '首重费',
                        ExpressLinePrice::TYPE_RANGE_NEXT_WEIGHT => '续单价',
                    };
                }) ($items, $i);

                $appends[] = $items->map(function ($region) use ($i) {
                    return [($region->prices->skip($i + 1)->first()?->price ?? 0) / 100, ($region->prices->skip($i)->first()?->price ?? 0) / 100];
                })->flatten()->prepend([$i + 1, $range, $unitWeight, $type])->flatten()->all();
            }

        } else {
            $first = $items->pluck('name')->flatten()->values()->prepend(['#', '', '', ''])->flatten()->all();
            $second = $items->map(function ($region) {
                return $region->areas->map(function ($area) {
                    return $area->country_name . $area->area_name . $area->sub_area_name;
                })->join('、');
            })->prepend(['#', '重量范围', '首重/单位重量', '价格类型'])->flatten()->all();

            $line = $items->first()?->prices?->count();

            $prices[] = $first;
            $prices[] = $second;
            $appends = [];
            for ($i = 0; $i < $line; $i++) {
                $range = (function ($items, $i) {
                    $price = $items->first()?->prices->skip($i)->first();
                    $start = $price->start / 1000;

                    if ($price->start === $price->end) {
                        return "[$start]";
                    } else {
                        $end = $price->end / 1000;
                        return "[$start, $end)";
                    }
                }) ($items, $i);
                // 这个值是 单位重量或者首重
                $unitWeight = (function ($items, $i) {
                    $price = $items->first()?->prices->skip($i)->first();
                    return $price->unit_weight
                        ? $price->unit_weight / 1000
                        : ($price->first_weight ? $price->first_weight / 1000 : '');
                }) ($items, $i);

                $type = (function ($items, $i) {
                    $price = $items->first()?->prices->skip($i)->first();

                    return match ($price->type) {
                        0 => '首重',
                        1 => '续重',
                        2 => '阶梯价格单价',
                        3 => '单位价格',
                        4 => '阶梯附加价格',
                        5 => '多级续重价格',
                        ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT => '首重费',
                        ExpressLinePrice::TYPE_RANGE_NEXT_WEIGHT => '续单价',
                        ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE => '阶梯基础价格',
                    };
                }) ($items, $i);

                $appends[] = $items->map(function ($region) use ($i) {
                    return ($region->prices->skip($i)->first()?->price ?? 0) / 100;
                })->flatten()->prepend([$i + 1, $range, $unitWeight, $type])->flatten()->all();
            }
        }

        $appends = collect($appends)->sortBy(function ($price) {
            if (preg_match('/\[([\d.]+)]/', $price[1], $match)) {
                return $match[1];
            }

            if (preg_match('/\[([\d.]+), ([\d.]+)\)/', $price[1], $match)) {
                return $match[1];
            }

            return 0;
        })->values()->transform(function ($value, $key) {
            $value[0] = $key + 1;

            return $value;
        })->all();

        $prices = array_merge($prices, $appends);

        $epl = ExpressLineModel::query()->find($eplId);

        $fileName = (remove_special_char($epl->name)) .'_prices_' . Carbon::now()->format('Ymd') . '_' . Str::random(6) . '.xlsx';

        $filePath = "/excel/prices/$fileName";

        $url = secure_asset(Storage::disk('admin_public')->url($filePath));

        /** @var ExcelExport $export */
        $export = ExcelExport::query()->create([
            'type' => ExcelExport::TYPE_PRICE_TABLE,
            'name' => $fileName,
            'url' => $url,
            'status' => ExcelExport::STATUS_EXPORTING,
        ]);

        dispatch(new RegionPriceExport($export, $prices, $merge));

        return true;
    }

    /**
     * @param int $id
     * @param UploadedFile $file
     * @return bool
     * @throws Exception
     */
    public function import(int $id, UploadedFile $file)
    {
        $dataList = $this->parseData($file);

        DB::beginTransaction();
        try {
            //根据渠道ID查询渠道及分区
            $expressLine = ExpressLineModel::query()->with(['regions'])->select(['id', 'mode'])->findOrFail($id);

            $mode = $expressLine->mode ?? 0;//计费模式
            $regionNames = $expressLine->regions->pluck('name')->toArray();//分区名称

            $dataList = $dataList->toArray();
            foreach ($dataList as $key => &$data) {

                //首行添加导入结果 第二行开始处理逻辑
                if ($key === 0) {
                    $data[] = '导入结果';
                    continue;
                }

                //校验数据格式
                $message = $this->verifyExcelData($data, $mode);
                if ($message) {
                    $data[] = $message;
                    continue;
                }

                //校验分区名称
                if (!in_array($data[0], $regionNames)) {
                    $data[] = '分区不存在';
                    continue;
                }

                //根据分区名称匹配分区
                $region = $expressLine->regions->filter(function ($item) use ($data) {
                    if ($item->name === $data[0]) {
                        return $item;
                    }
                });

                if ($region->isEmpty()) {
                    $data[] = '分区不存在';
                    continue;
                }

                $region = $region->first();

                //首重续重模式
                if ($mode === ExpressLineModel::MODE_1) {

                    //价格类型 起始重量 截止重量 单位续重 单价
                    [$start, $end, $unitWeight, $price, $type] = $this->parseRange($data, $mode);

                    if ($type === '') {
                        $data[] = '价格类型不正确';
                        continue;
                    }

                    //根据价格类型、重量区间、单位续重查询数据
                    $priceQuery = $region->prices()
                        ->where('type', $type)
                        ->where('end', $end * 1000);

                    if ($type === ExpressLinePrice::TYPE_FIRST_WEIGHT) {
                        //首重起始重量等于截止重量
                        $priceQuery->where('start', $end * 1000);
                    } else {
                        //续重匹配单位重量
                        $priceQuery->where('start', $start * 1000);
                    }

                    $item = $priceQuery->first();

                    if (empty($item)) {
                        $data[] = '重量区间不正确';
                        continue;
                    }

                    $unitWeight *= 1000;
                    if ($type === ExpressLinePrice::TYPE_NEXT_WEIGHT && (int)$unitWeight !== $item->unit_weight) {
                        $data[] = '单位续重不正确';
                        continue;
                    }

                    //更新价格
                    $item->update(['price' => $price * 100]);
                }

                //阶梯价格模式
                if ($mode === ExpressLineModel::MODE_2) {

                    [$start, $end, $price, $basePrice] = $this->parseRange($data, $mode);

                    //根据重量区间查询数据
                    $prices = $region->prices()
                        ->where('start', $start * 1000)
                        ->where('end', $end * 1000)
                        ->get();

                    if ($prices->isEmpty()) {
                        $data[] = '重量区间不正确';
                        continue;
                    }

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
                }

                //阶梯首重续重模式
                if ($mode === ExpressLineModel::MODE_RANGE_FIRST_NEXT) {

                    //起始重量 截止重量 首重重量 首重价格 单位续重重量 续重单价
                    [$start, $end, $firstWeight, $basePrice, $unitWeight, $price] = $this->parseRange($data, $mode);

                    //根据重量区间查询数据
                    $prices = $region->prices()
                        ->where('start', $start * 1000)
                        ->where('end', $end * 1000)
                        ->get();

                    if ($prices->isEmpty()) {
                        $data[] = '重量区间不正确';
                        continue;
                    }

                    //更新价格
                    $error = '';
                    $prices->each(function ($item) use ($firstWeight, $basePrice, $unitWeight, $price, &$error) {
                        $amount = $price;
                        //首重价格
                        if ($item->type === ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT) {
                            //判断首重重量
                            $firstWeight *= 1000;
                            if ((int)$firstWeight !== $item->first_weight) {
                                $error = '首重重量不正确';
                                return false;
                            }

                            $amount = $basePrice;
                        }

                        //续重单价
                        if ($item->type === ExpressLinePrice::TYPE_RANGE_NEXT_WEIGHT) {
                            //判断续重重量
                            $unitWeight *= 1000;
                            if ((int)$unitWeight !== $item->unit_weight) {
                                $error = '单位续重重量不正确';
                                return false;
                            }
                        }

                        if (empty($error)) {
                            $item->update(['price' => $amount * 100]);
                        }
                    });

                    if ($error) {
                        $data[] = $error;
                        continue;
                    }
                }

                $data[] = '更新成功';
            }

            //重新组装导入额数据并导出Excel表格
            $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $fileName = $basename . '_import_result.xlsx';
            $filePath = "/excel/prices/$fileName";

            $url = secure_asset(Storage::disk('admin_public')->url($filePath));

            /** @var ExcelExport $export */
            $export = ExcelExport::query()->create([
                'type' => ExcelExport::TYPE_PRICE_TABLE,
                'name' => $fileName,
                'url' => $url,
                'status' => ExcelExport::STATUS_EXPORTING,
            ]);

            dispatch(new RegionPriceExport($export, $dataList));

            DB::commit();
        } catch (\Throwable $throwable) {
            DB::rollBack();

            info('价格表导入失败', ['message' => $throwable->getMessage()]);

            throw new AccidentException('导入失败，请检查Excel数据格式', Code::OPERATE_FAIL);
        }

        return true;
    }

    public function verifyExcelData(array $price, int $mode): string|null
    {
        $data = $this->parseRange($price, $mode);

        foreach ($data as $value) {
            if (!is_numeric($value) && empty($value)) {
                return '数据不能为空';
            }

            if (!is_numeric($value)) {
                return '数据格式不正确';
            }
        }

        return null;
    }

    /**
     * @param int $id
     * @param UploadedFile $file
     * @return bool
     * @throws Exception
     */
    public function import0704(int $id, UploadedFile $file)
    {
        $data = $this->parseData($file);

        $regionNames = $data[0]->skip(4)->values();
        $regions = collect([]);
        foreach ($regionNames as $name) {
            $region = ExpressLineRegion::query()
                ->where('express_line_id', $id)
                ->where('name->zh_CN', $name)
                ->first();

            $regions->add($region);
        }

        if ($data->count() > 20) {
            collect($data)->skip(1)->chunk(10)->each(function ($items) use ($regions) {
                dispatch(new ImportExpressLinePrice($items, $regions));
                return true;
            });

            return true;
        }

        DB::beginTransaction();
        try {
            foreach ($data as $key => $datum) {
                if ($key === 0) {
                    continue;
                }

                [$start, $end, $uw, $type] = $this->parseRange($datum);

                $price = $datum->skip(4)->values();
                // 首重价格 单位价格
                if ($start === $end && !$uw) {
                    /** @var ExpressLineRegion $region */
                    foreach ($regions as $count => $region) {
                        $region->prices()
                            ->where('start', $start * 1000)
                            ->where('end', $end * 1000)
                            ->whereIn('type', [ExpressLinePrice::TYPE_FIRST_WEIGHT, ExpressLinePrice::TYPE_UNIT_WEIGHT])
                            ->update(['price' => $price[$count] * 100]);
                    }
                    // 续重价格 和阶梯范围首重 续重价格
                } elseif ($start < $end && ($uw || $type == ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT)) {
                    /** @var ExpressLineRegion $region */
                    foreach ($regions as $count => $region) {
                        // 阶梯范围首重
                        if ($type === ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT) {
                            $region->prices()
                                ->where('start', $start * 1000)
                                ->where('end', $end * 1000)
                                ->where('first_weight', ($uw ?: 0) * 1000)
                                ->where('type', ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT)
                                ->update(['price' => $price[$count] * 100]);
                        } else {
                            // 普通续重 阶梯范围续重
                            $region->prices()
                                ->where('start', $start * 1000)
                                ->where('end', $end * 1000)
                                ->where('unit_weight', $uw * 1000)
                                ->whereIn('type', [
                                    ExpressLinePrice::TYPE_NEXT_WEIGHT,
                                    ExpressLinePrice::TYPE_RANGE_NEXT_WEIGHT,
                                ])
                                ->update(['price' => $price[$count] * 100]);
                        }
                    }
                    //阶梯价格
                } elseif ($start < $end && !$uw && !$type) {
                    /** @var ExpressLineRegion $region */
                    foreach ($regions as $count => $region) {
                        $region->prices()
                            ->where('start', $start * 1000)
                            ->where('end', $end * 1000)
                            ->whereIn('type', [
                                    ExpressLinePrice::TYPE_GRADE_WEIGHT,
                                    ExpressLinePrice::TYPE_GRADE_WEIGHT_APPEND,
                                ]
                            )
                            ->update(['price' => $price[$count] * 100]);
                    }
                    // 阶梯价格的基础价格 和 单价
                } elseif ($start < $end && !$uw && $type === ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE) {
                    /** @var ExpressLineRegion $region */
                    foreach ($regions as $count => $region) {
                        // 每两个价格为一组价格 基础价格在前 单位价格在后
                        if ($count % 2 == 0) {
                            $region->prices()
                                ->where('start', $start * 1000)
                                ->where('end', $end * 1000)
                                ->where('type', ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE)
                                ->update(['price' => $price[$count] * 100]);

                            $region->prices()
                                ->where('start', $start * 1000)
                                ->where('end', $end * 1000)
                                ->where('type', ExpressLinePrice::TYPE_GRADE_WEIGHT)
                                ->update(['price' => $price[$count + 1] * 100]);
                        }
                    }
                    // 多级续重
                } elseif (!$start && !$end && $uw) {
                    /** @var ExpressLineRegion $region */
                    foreach ($regions as $count => $region) {
                        $region->prices()
                            ->where('unit_weight', $uw * 1000)
                            ->where('type', ExpressLinePrice::TYPE_GRADE_NEXT_WEIGHT)
                            ->update(['price' => $price[$count] * 100]);
                    }
                }
            }
        } catch (\Throwable $throwable) {
            DB::rollBack();

            info('价格表导入失败', ['message' => $throwable->getMessage()]);

            throw new AccidentException('导入失败，请检查Excel数据格式', Code::OPERATE_FAIL);
        }

        DB::commit();
        return true;
    }

    /**
     * @param array $price
     * @param int $mode
     * @return array
     */
    public function parseRange(array $price, int $mode): array
    {
        $array = [];

        //首重续重模式
        if ($mode === ExpressLineModel::MODE_1) {
            $type = match($price[1]) {
                '首重' => ExpressLinePrice::TYPE_FIRST_WEIGHT,
                '续重' => ExpressLinePrice::TYPE_NEXT_WEIGHT,
                default => '',
            };

            //起始重量 截止重量 单位续重 单价 价格类型
            $array = [$price[2], $price[3], $price[4], $price[5], $type];
        }

        //阶梯价格模式
        if ($mode === ExpressLineModel::MODE_2) {
            //起始重量 截止重量 单价 操作费
            $array = [$price[1], $price[2], $price[3], $price[4]];
        }

        //阶梯首重续重模式
        if ($mode === ExpressLineModel::MODE_RANGE_FIRST_NEXT) {
            //起始重量 截止重量 首重重量 首重价格 单位续重重量 续重单价
            $array = [$price[1], $price[2], $price[3], $price[4], $price[5], $price[6]];
        }

        return $array;
    }

    /**
     * @param Collection $price
     * @return array
     */
    public function parseRange0704(Collection $price): array
    {
        $range = $price[1];
        $unitWeight = $price[2];

        $type = match($price[3]) {
            '阶梯价格' => ExpressLinePrice::TYPE_GRADE_WEIGHT_BASE,
            '首重费' => ExpressLinePrice::TYPE_RANGE_FIRST_WEIGHT,
            '续单价'=> ExpressLinePrice::TYPE_RANGE_NEXT_WEIGHT,
            default => '',
        };

        if (preg_match('/\[([\d.]+)]/', $range, $match)) {
            return [$match[1], $match[1], $unitWeight, $type];
        }

        if (preg_match('/\[([\d.]+), ([\d.]+)\)/', $range, $match)) {
            return [$match[1], $match[2], $unitWeight, $type];
        }

        return [0, 0, $unitWeight, $type];
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
                if (!$row[0]) {
                    break;
                }
                $items->push(collect($row));
            }
            //删除第一行说明性数据
            // unset($items[0]);

            return $items;
        } catch (\Throwable $throwable) {
            throw new AccidentException('模板数据异常，请检查模板数据是否填写完整与正确', Code::OPERATE_FAIL);
        }
    }

    /**
     * @return string[]
     */
    protected function rules()
    {
        return [
            '*.region_id' => 'required',
            '*.prices' => 'required|array',
            '*.prices.*.id' => 'required',
            '*.prices.*.price' => 'required|nullable|numeric',
        ];
    }
}
