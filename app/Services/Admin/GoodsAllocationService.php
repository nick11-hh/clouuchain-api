<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\WarehouseGoodsAllocationArea as Area;
use \Exception;
use App\Http\Resources\Admin\GoodsAllocationDetailList;
use App\Models\Order;
use App\Models\WarehouseAddress;
use App\Models\WarehouseGoodsAllocation;
use App\Models\WarehouseGoodsAllocation as Allocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class GoodsAllocationService extends BaseService
{
    protected Allocation $allocation;

    public function __construct()
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = new Area();
        $this->query = $this->model->newQuery();
        $this->allocation = new Allocation();
        $this->setFilterRules();
    }

    /**
     * @param int $warehouseId
     * @param int $id
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function showArea(int $warehouseId, int $id)
    {
        return Area::where('warehouse_id', $warehouseId)->findOrFail($id);
    }

    /**
     * @param int $warehouseId
     * @param int $id
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function showAreaDetails(int $warehouseId, int $id)
    {
        return Allocation::query()
            ->where('warehouse_id', $warehouseId)
            ->where('area_id', $id)
            ->when(request()->input('keyword'), function (Builder $query) {
                $query->where('code', 'like', '%' . request()->input('keyword') . '%');
            })
            ->orderByRaw("SUBSTRING_INDEX(`code`, '-', 1) , `column`, `row` ASC")
            ->paginate();
    }

    /**
     * @param int $id
     * @param int $status
     * @return int
     */
    public function areaUnlock(int $id, int $status)
    {
        Area::query()
            ->where('id', $id)
            ->update(['is_locked' => $status]);

        return Allocation::query()
            ->where('area_id', $id)
            ->update(
                ['is_locked' => $status]
            );
    }



    /**
     * @param int $warehouseId
     * @return mixed
     */
    public function getOneUsableGoodsAllocation(int $warehouseId)
    {
        return Allocation::query()
            ->where('warehouse_id', $warehouseId)
            ->orderByRaw("SUBSTRING_INDEX(`code`, '-', 1) , `column`, `row` ASC")
            ->notUsed()
            ->notLocked()
            ->first();
    }

    /**
     * @param int $warehouseId
     * @param int|null $areaId
     * @param string $code
     * @param int|string|null $userId
     * @param int $isBig
     * @param array $size
     * @param int $weight
     * @return Collection
     */
    public function searchUsableGoodsAllocation(
        int        $warehouseId,
        int        $areaId = null,
        string     $code = '',
        int|string $userId = null,
        int        $isBig = 0,
        array      $size = [],
        int        $weight = 0
    )
    {
        /** @var WarehouseAddress $warehouse */
        $warehouse = WarehouseAddress::query()->findOrFail($warehouseId);
        $rule = $warehouse->big_rule;
        $sizeRule = $warehouse->size_rule;
        $weightRule = $warehouse->weight_rule;
        $ruleSize = $warehouse->location_size;
        $ruleWeight = $warehouse->location_weight;
        $noOwner = 0;
        $noOwnerCount = Area::query()->where('no_package_special', 1)->count();
        $weight *= 1000;

        /** @var Collection $locations */
        $locations = Allocation::query()
            ->where('warehouse_id', $warehouseId)
            ->when($areaId, function ($query) use ($areaId) {
                $query->where('area_id', $areaId);
            })
            ->orderByRaw("SUBSTRING_INDEX(`code`, '-', 1) , `column`, `row` ASC")
            ->notLocked()
            ->where(function ($query) use ($userId, $isBig, &$noOwner, $noOwnerCount) {
                if ($isBig) {
                    $query->noPackageSpecialNo();
                    return;
                }

                if ($noOwnerCount > 0) {
                    if ($userId) {
                        $query->noPackageSpecialNo();
                    } else {
                        $query->noPackageSpecialYes();
                        $noOwner = 1;
                    }
                }
            })
            ->when($isBig, function ($query) {
                $query->whereHas('area', function ($query) {
                    $query->where('for_big', 1);
                });
            })
            ->when(
                $rule === 1 && $isBig == 0 && (($sizeRule && $size) || ($weightRule && $weight)) && !$noOwner,
                function ($query) use ($ruleSize, $size, $weightRule, $weight, $sizeRule, $ruleWeight) {
                    if ($sizeRule) {
                        $t = ['length' => 0, 'width' => 1, 'height' => 2];
                        $sCompare = collect($ruleSize)->reduce(function ($r, $v, $k) use ($size, $t) {
                            if ($v !== null && $size[$t[$k]] && $size[$t[$k]] > $v) {
                                return ++$r;
                            }
                            return $r;
                        }, 0);
                    }

                    if ($weightRule) {
                        $wCompare = $weight > $ruleWeight ? 1 : 0;
                    }

                    if (($sCompare ?? 0) || ($wCompare ?? 0)) {
                        $query->whereHas('area', function ($query) {
                            $query->where('for_big', 1);
                        });
                    } else {
                        $query->whereHas('area', function ($query) {
                            $query->where('for_big', 0);
                        });
                    }
                })
            ->when($isBig == 0 && $rule === 0, function ($query) {
                $query->whereHas('area', function ($query) {
                    $query->where('for_big', 0);
                });
            })
            ->where('code', 'like', "%$code%")
            ->limit(20)
            ->get();

        if ($locations->isNotEmpty()) {
            $locations = $locations->reject(function ($location) use ($warehouseId) {
                if (Cache::has("Location-$warehouseId-{$location['code']}")) {
                    return true;
                }

                return false;
            })->values();

            if ($locations->isEmpty()) {
                return $locations;
            }

            Cache::put("Location-$warehouseId-{$locations[0]['code']}", 1, 5);
        }

        return $locations;
    }

    /**
     * 添加
     *
     * @param int $warehouseId
     * @param array $data
     * @return bool
     * @throws \Throwable
     */
    public function add(int $warehouseId, array $data): bool
    {
        if (($data['type'] ?? 0) == 1) {
            validator($data, $this->addCustomRules())->validate();
        } else {
            validator($data, $this->addRules(), ['number.regex' => '编号只能为字母、数字、下划线'])->validate();
        }

        throw_unless(
            WarehouseAddress::isValid([$warehouseId]),
            new AccidentException('仓库不存在')
        );

        $this->model::validateUniqueOrFail('number', $data['number']);

        if (($data['type'] ?? 0) == 1) {
            return DB::transaction(function () use ($data, $warehouseId) {
                /** @var Area $area */
                $area = Area::query()->create([
                    'type' => 1,
                    'warehouse_id' => $warehouseId,
                    'number' => $data['number'],
                    'column' => 0,
                    'row' => 0,
                    'counts' => 0,
                ]);

                if (($data['max_count'] ?? 1) > 1) {
                    $area->allocations()->update(['max_count' => $data['max_count'] ?? 1]);
                }

                return true;
            });
        } else {
            return DB::transaction(function () use ($data, $warehouseId) {
                if ($data['column'] * $data['row'] > 10000) {
                    throw new AccidentException('最大支持货位数量为10000');
                }

                /** @var Area $area */
                $area = Area::create([
                    'warehouse_id' => $warehouseId,
                    'number' => $data['number'],
                    'column' => $data['column'],
                    'row' => $data['row'],
                    'counts' => $data['column'] * $data['row'],
                ]);

                $locations = [];
                for ($i = 1; $i <= $data['column']; $i++) {
                    for ($j = 1; $j <= $data['row']; $j++) {
                        $locations[] = [
                            'warehouse_id' => $warehouseId,
                            'area_id' => $area->getKey(),
                            'column' => $i,
                            'row' => $j,
                            'code' => sprintf('%s-%s-%s', $data['number'], $this->withZero($i), $this->withZero($j)),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                collect($locations)->chunk(100)->each(function ($l) use ($area) {
                    $area->allocations()->insert($l->toArray());
                });


                if (($data['max_count'] ?? 1) > 1) {
                    $area->allocations()->update(['max_count' => $data['max_count'] ?? 1]);
                }

                return true;
            });
        }
    }

    /**
     * 添加
     *
     * @param int $warehouseId
     * @param int $id
     * @param array $data
     * @return bool
     * @throws Exception
     * @throws \Throwable
     */
    public function update(int $warehouseId, int $id, array $data): bool
    {
        if (($data['type'] ?? 0) == 1) {
            validator($data, $this->updateCustomRules())->validate();
        } else {
            validator($data, $this->updateRules())->validate();
        }

        throw_unless(
            WarehouseAddress::isValid([$warehouseId]),
            new AccidentException('仓库不存在')
        );

        /** @var Area $area */
        $area = Area::where('warehouse_id', $warehouseId)->findOrFail($id);


        if (($data['max_count'] ?? 1) >= 1) {
            $area->allocations()->update(['max_count' => $data['max_count'] ?? 1]);
        }

        if (($data['type'] ?? 0) == 1) {
            return true;
        } else {
            if ($data['column'] * $data['row'] > 10000) {
                throw new AccidentException('最大支持货位数量为10000');
            }

            if (!$this->isAreaChanged($area, $data)) {
                return true;
            }

            $this->checkIfAreaIncrement($area, $data);

            return DB::transaction(function () use ($data, $area) {
                $originColumn = $area->column;
                $originRow = $area->row;

                $area->update([
                    'column' => $data['column'],
                    'row' => $data['row'],
                    'counts' => $data['column'] * $data['row'],
                ]);

                $locations = [];
                //first, append the columns
                for ($i = $originColumn + 1; $i <= $data['column']; $i++) {
                    for ($j = 1; $j <= $originRow; $j++) {
                        $locations[] = [
                            'warehouse_id' => $area->warehouse_id,
                            'area_id' => $area->getKey(),
                            'column' => $i,
                            'row' => $j,
                            'code' => sprintf('%s-%s-%s', $data['number'], $this->withZero($i), $this->withZero($j)),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                //second, append the rows
                for ($i = 1; $i <= $area->column; $i++) {
                    for ($j = $originRow + 1; $j <= $area->row; $j++) {
                        $locations[] = [
                            'warehouse_id' => $area->warehouse_id,
                            'area_id' => $area->getKey(),
                            'column' => $i,
                            'row' => $j,
                            'code' => sprintf('%s-%s-%s', $data['number'], $this->withZero($i), $this->withZero($j)),
                            'company_id' => \auth()->user()->company_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                collect($locations)->chunk(100)->each(function ($l) use ($area) {
                    $area->allocations()->insert($l->toArray());
                });

                return true;
            });
        }
    }

    /**
     * 添加
     *
     * @param int $areaId
     * @param array $data
     * @return bool
     * @throws Exception
     */
    public function addCustomLocation(int $areaId, array $data): bool
    {
        validator($data, $this->addLocationRules())->validate();

        /** @var Area $area */
        $area = Area::query()->findOrFail($areaId);

        if ($area->type !== 1) {
            throw new AccidentException('当前区域不是自定义货区');
        }

        return DB::transaction(function () use ($data, $area) {
            collect($data['codes'])->chunk(100)->each(function ($v) {
                $counts = $v->countBy();

                foreach ($counts as $k => $count) {
                    if ($count > 1) {
                        throw new AccidentException('编码:code 重复');
                    }
                }

                $counts = WarehouseGoodsAllocation::query()
                    ->selectRaw('code, count(*) as count')
                    ->whereIn('code', $v)
                    ->groupBy('code')
                    ->get();

                foreach ($counts as $count) {
                    if ($count->count > 0) {
                        throw new AccidentException('编码:code 已存在', Code::OPERATE_FAIL);
                    }
                }
            });

            $last = Allocation::query()->where('area_id', $area->getKey())->first();

            $locations = [];
            foreach ($data['codes'] as $code) {
                $locations[] = [
                    'warehouse_id' => $area->warehouse_id,
                    'area_id' => $area->getKey(),
                    'column' => 0,
                    'row' => 0,
                    'code' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'max_count' => $last->max_count ?? 1,
                ];
            }

            collect($locations)->chunk(100)->each(function ($l) use ($area) {
                $area->allocations()->insert($l->toArray());
            });

            return true;
        });
    }


    /**
     * 设置大货包裹专区
     * @param int $warehouseId
     * @param $data
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Throwable
     */
    protected function setBigArea(int $warehouseId, $data)
    {
        validator($data, [
            'big_area_ids' => 'nullable|array',
            'big_area_ids.*' => 'required_with:big_area_ids|int',
        ])->validate();

        if (!empty($data['big_area_ids'])) {
            throw_unless(
                $this->model::isValid($data['big_area_ids']),
                new AccidentException('区域不正确')
            );
        }

        return DB::transaction(function () use ($warehouseId, $data) {
            //更新正常区域
            $this->model::query()
                ->where('warehouse_id', $warehouseId)
                ->update(['for_big' => 0]);
            //更新无人认领包裹专区
            if (!empty($data['big_area_ids'])) {
                $this->model::query()->where('warehouse_id', $warehouseId)
                    ->whereIn('id', $data['big_area_ids'])
                    ->update(['for_big' => 1]);
            }

            return true;
        });
    }


    /**
     * @param int $warehouseId
     * @param bool $status
     * @return bool
     */
    public function updateCustomLocation(int $warehouseId, bool $status)
    {
        return DB::transaction(function () use ($warehouseId, $status) {
            return WarehouseAddress::query()
                ->whereKey($warehouseId)
                ->update(['custom_location' =>  (int) $status]);
        });
    }

    /**
     * @param int $warehouseId
     * @return mixed
     */
    public function indexOfAreas(int $warehouseId)
    {
        return Area::query()
            ->with('allocations:id,area_id,used_count,max_count')
            ->where('warehouse_id', $warehouseId)
            ->when($this->request->filled('keyword'), function ($query) {
                $query->whereHas('allocations', function ($query) {
                    $query->where('code', 'like', "%{$this->request->keyword}%");
                });
            })
            ->orderBy('index')
            ->orderBy('number')
            ->paginate();
    }

    /**
     * @param int $warehouseId
     * @param int $id
     * @return bool
     * @throws \Throwable
     */
    public function deleteArea(int $warehouseId, int $id): bool
    {
        /** @var Area $area */
        $area = Area::where('warehouse_id', $warehouseId)->findOrFail($id);

        return DB::transaction(function () use ($area) {
            $area->allocations()->delete();

            return $area->delete();
        });
    }

    /**
     * @param int $id
     * @return bool
     * @throws \Throwable
     */
    public function deleteCustomLocation(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $location = WarehouseGoodsAllocation::with('area')->findOrFail($id);

            if ($location->area->type == 1) {
                return $location->delete();
            }

            throw new AccidentException('该货位区域不是自定义区域');
        });
    }

    protected function withZero(int $num)
    {
        if ($num > 99) {
            return $num;
        }

        return substr('0' . $num, -2);
    }

    /**
     * @param Area $area
     * @param array $data
     * @throws Exception
     */
    protected function checkIfAreaIncrement(Area $area, array $data): void
    {
        if ($data['column'] < $area->column || $data['row'] < $area->row) {
            throw new AccidentException('区域货位只能增加');
        }
    }

    /**
     * @param Area $area
     * @param array $data
     * @return bool
     */
    protected function isAreaChanged(Area $area, array $data): bool
    {
        if ((int)$data['column'] === $area->column && (int)$data['row'] === $area->row) {
            return false;
        }

        return true;
    }

    /**
     * @param int $id
     * @param int $status
     * @return bool
     */
    public function setLock(int $id, int $status)
    {
        /** @var Allocation $location */
        $location = Allocation::query()->findOrFail($id);

        return $location->update([
                'is_locked' => (int)(bool)$status,
            ]) !== false;
    }



    /**
     * @param $code
     * @param $packageId
     * @return mixed
     */
    public static function packageIsOnLocation($code, $packageId)
    {
        /** @var Allocation $location */
        $location = Allocation::query()->with('warehouse')->where('code', $code)->first();

        if(!$location) return false;

        $sub = Order::query()->selectRaw('id as oId, status as oS, pick_status');

        $package =  Package::query()
            ->with('owner')
            ->where(function (Builder $query) {
                $query->whereIn('status',
                    [
                        Package::STATUS_ALREADY_PACK,
                        Package::STATUS_ALREADY_STORAGE,
                        Package::STATUS_WAIT_STORAGE,
                        Package::STATUS_NO_OWNER,
                    ]
                )/*->orWhereHas('order', function ($query) {
                    $query->where('status', Order::WAIT_WAREHOUSE_PACK);
                })*/
                ;
            })
            ->leftjoinSub($sub, 'o', 'o.oId', '=', 'jiyun_package.order_id')
            ->when($location->warehouse->off_shelf_status, function ($query) {
                $query->where(function ($query) {
                    $query->where('o.pick_status', 0)->orWhereNull('o.pick_status');
                });
            })
            ->where(function ($query) {
                $query->where('o.oS', 1)->orWhereNull('o.oS');
            })
            ->where('location', $location->code)
            ->whereKey($packageId)
            ->first();

        return (bool)$package;
    }


    /**
     * @param Request $request
     * @return mixed
     */
    public function locationList(Request $request)
    {
        $query = Allocation::with(['area', 'area.warehouse']);

        if ($request->filled('warehouse_id')) {
            $query->whereHas('area', function ($query) use ($request) {
                $query->where('warehouse_id', $request->input('warehouse_id'));
            });
        }

        if ($request->filled('area_id')) {
            $query->where('area_id', $request->input('area_id'));
        }

        if ($request->filled('keyword')) {
            $query->where('code', 'like', "%{$request->input('keyword')}%");
        }

        if ($request->filled('lock')) {
            if ($request->input('lock') == 1) {
                $query->where('is_locked', 1);
            } elseif ($request->input('lock') == 0) {
                $query->where('is_locked', 0);
            }
        }

        if ($request->filled('used')) {
            if ($request->input('used') == 1) {
                $query->where('used_count', '>', 0);
            }
        }

        $data = $query->get();

        $lockCount = $data->filter(function ($location) {
            return $location->is_locked;
        })->count();

        $usedCount = $data->filter(function ($location) {
            return $location->used_count > 0;
        })->count();

        /** @var LengthAwarePaginator $pageData */
        $pageData = $query->paginate($request->size ?? 10);

        return GoodsAllocationDetailList::collection($pageData)
            ->additional(['status' => 1, 'msg' => 'success', 'lock_count' => $lockCount, 'used_count' => $usedCount]);
    }

    public function getNumberInWarehouse(int $warehouseId)
    {
        return Area::query()->where('warehouse_id', $warehouseId)
            ->select(['id', 'number'])
            ->get();
    }

    /**
     * @param array $data
     * @return mixed
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateIndex(array $data)
    {
        $data = validator($data, $this->sortRules())->validate();

        return DB::transaction(function () use ($data) {
            foreach ($data['sort'] as $datum) {
                Area::query()
                    ->findOrFail($datum['id'])
                    ->update(['index' => $datum['index']]);
            }

            return true;
        });
    }

    public function getLocationAreaTree(int $warehouseId)
    {
        return Area::query()->with(['allocations'])->where('warehouse_id', $warehouseId)
            ->select(['id', 'number'])
            ->get();
    }

    /**
     * @return mixed
     */
    public function resetIndex()
    {
        return Area::query()->update(['index' => 0]);
    }

    private function addRules()
    {
        return [
            'number' => 'required|string|regex:/[A-Za-z0-9\-_]{1,15}/|max:15',
            'column' => 'required|integer|gt:0|max:500',
            'row' => 'required|integer|gt:0|max:500',
            'max_count' => 'sometimes|nullable|numeric|gte:1',
        ];
    }

    private function addCustomRules()
    {
        return [
            'type' => 'required|in:1',
            'number' => 'required|string|max:15',
            'max_count' => 'sometimes|nullable|numeric|gte:1',
        ];
    }

    private function addLocationRules()
    {
        return [
            'codes' => 'required|array',
            'codes.*' => 'required|string|max:30',
        ];
    }

    private function updateRules()
    {
        return [
            'number' => 'required|string|max:15',
            'column' => 'required|integer|gt:0|max:500',
            'row' => 'required|integer|gt:0|max:500',
            'max_count' => 'sometimes|nullable|numeric|gte:1',
        ];
    }

    private function updateCustomRules()
    {
        return [
            'number' => 'required|string|max:15',
            'max_count' => 'sometimes|nullable|numeric|gte:1',
        ];
    }

    public function sortRules()
    {
        return [
            'sort.*.id' => 'required',
            'sort.*.index' => 'required|integer',
        ];
    }
}
