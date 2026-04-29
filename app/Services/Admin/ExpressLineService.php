<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:40
 */

namespace App\Services\Admin;

use App\Console\Commands\UpdateCountryArea;
use App\Exports\ExpressLineExport;
use App\Lib\Code;
use App\Lib\Language;
use App\Models\CompanyExpressModel;
use App\Models\Country;
use App\Models\ExpressLineModel;
use App\Models\ExpressLineLabelsModel;
use App\Models\ExpressLineRegion;
use App\Models\ExpressLineRule;
use App\Models\LogisticsChannelModel;
use App\Models\Order;
use App\Models\OrderDockingRecord;
use App\Models\OrderDockingType;
use App\Models\PackageProp;
use App\Models\PayOnDeliveryConfig;
use App\Models\RechargeApply;
use App\Models\Timezone;
use App\Models\WarehouseAddress;
use App\Services\C2TTranslate;
use App\Services\ExpressCompanies\LTExp\LTExp;
use App\Models\ThirdPartyChannel;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Khsing\World\Models\CountryLocale;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;
use App\Exceptions\AccidentException;

class ExpressLineService extends BaseService
{
    use HasNameUniqueValidation,
        HasStatusSetting;

    public static $relation = [
        'types' => 'props',
    ];

    public static $casts = [
        'first_weight' => 1000,
        'next_weight' => 1000,
        'first_money' => 100,
        'next_money' => 100,
    ];

    protected $orderBy = ['enabled' => 'desc', 'id' => 'asc'];

    protected $filterRules = [
        'enabled' => ['=', 'enabled'],
    ];

    public function __construct()
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = new ExpressLineModel();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    /**
     * 列表
     * @return mixed
     */
    public function index(int $groupId = null)
    {
        $this->query->with(
            [
                'countries',// 联国家数据
//                'warehouses',// 关联仓库数据
//                'warehouses.countries',// 关联仓库的国家数据（嵌套预加载）
                'props',// 关联属性数据
                'icon',// 关联图标数据
                'priceGrade',// 关联价格等级数据
            ]
        )->where('is_hidden', 0);// 只查询未隐藏的线路

        $this->search();

        $this->queryParser();

        $this->query->when($groupId, function ($query) use ($groupId) {
           $query->where('group_id', $groupId);
        });

        return parent::index();
    }

    /**
     * 获取启用的运费模板
     */
    public function list()
    {
        $this->query->with(
            [
                'props',// 关联属性数据
            ]
        )->where('enabled', 1);// 只查询启用的
        $this->search();
        return parent::all();
    }

    public function simple()
    {
        return parent::index();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getSimpleList()
    {
        return ExpressLineModel::query()
            ->select(['id', 'name'])
            ->latest()
            ->get();
    }

    /**
     * 新建
     * @param array $data
     * @return bool
     * @throws Throwable
     */
    public function add(array $data): bool
    {
        validator($data, $this->rules())->validate();

        //$this->model::validateUniqueOrFail('cn_name', $data['cn_name']);

        throw_unless(Country::isValid($data['countries']), new AccidentException('国家错误！', Code::OPERATE_FAIL));

        throw_unless(PackageProp::isValid($data['types']), new AccidentException('包裹属性错误！', Code::OPERATE_FAIL));

        !empty($data['labels']) && throw_unless(ExpressLineLabelsModel::isValid($data['labels']), new AccidentException('标签错误！', Code::OPERATE_FAIL));

        throw_unless(WarehouseAddress::isValid($data['warehouses']), new AccidentException('仓库地址错误', Code::OPERATE_FAIL));

        throw_unless(
            $this->onlyHasWarehousesCountries($data['warehouses'], $data['countries']),
            new AccidentException('当前选择的仓库地址不支持所选国家', Code::OPERATE_FAIL)
        );

        return DB::transaction(function () use ($data) {
            /** @var ExpressLineModel $expressLine */
            $expressLine = new $this->model($this->fillData($data));

            $expressLine->save();

            $this->updateRelations($expressLine, $data);

            $this->updatePriceGrade($expressLine, $data);

            return true;
        });
    }

    /**
     * 更新资料
     * @param int $id
     * @param array $data
     * @return bool
     * @throws Throwable
     */
    public function updateInformation(int $id, array $data): bool
    {
        validator($data, $this->rules())->validate();

        //$this->model::validateUniqueOrFail('cn_name', $data['cn_name'], $id);

        throw_unless(Country::isValid($data['countries']), new AccidentException('国家错误', Code::OPERATE_FAIL));

        throw_unless(PackageProp::isValid($data['types']), new AccidentException('包裹属性错误', Code::OPERATE_FAIL));

        !empty($data['labels']) && throw_unless(ExpressLineLabelsModel::isValid($data['labels']), new AccidentException('标签错误！', Code::OPERATE_FAIL));

        throw_unless(WarehouseAddress::isValid($data['warehouses']), new AccidentException('仓库地址错误', Code::OPERATE_FAIL));

        throw_unless(
            $this->onlyHasWarehousesCountries($data['warehouses'], $data['countries']),
            new AccidentException('当前选择的仓库地址不支持所选国家', Code::OPERATE_FAIL)
        );

        /** @var ExpressLineModel  $expressLine */
        $expressLine = $this->model::findOrFail($id);

        $expressLine->update($this->fillData($data));

        $res = $expressLine->save();

        if ($res) {
            $this->updateRelations($expressLine, $data);

            $this->updatePriceGrade($expressLine, $data);
        }

        return $res;
    }

    /**
     * @param ExpressLineModel $expressLine
     * @param array $data
     */
    protected function updateRelations(ExpressLineModel $expressLine, array $data)
    {
        $expressLine->countries()->sync(collect($data['countries'])->unique());
        $expressLine->props()->sync(collect($data['types'])->unique());
        !empty($data['labels']) && $expressLine->labels()->sync(collect($data['labels'])->unique());
        $expressLine->warehouses()->sync(collect($data['warehouses'])->unique());
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

        /** @var ExpressLineModel $expressLine */
        $expressLine = $this->model::query()->findOrFail($id);

        $expressLine->setTranslations('name', [$data['language'] => $data['name']]);
        $expressLine->setTranslations('cn_name', [$data['language'] => $data['name']]);
        $expressLine->setTranslations('en_name', [$data['language'] => $data['name']]);
        $expressLine->setTranslations('remark', [$data['language'] => $data['remark'] ?? '']);

        return $expressLine->save();
    }

    /**
     * 获得仓库国家列表
     *
     * @param  array  $warehouseIds
     * @return Collection
     */
    public function getWarehouseCountryList(array $warehouseIds): Collection
    {
        return WarehouseAddress::with([
            'countries' => function ($query) {
                $query->where('enabled', 1);
            },
        ])
            ->whereIn('id', $warehouseIds)
            ->get()
            ->pluck('countries')
            ->flatten()->unique(function ($country) {
                return $country->cn_name;
            });
    }

    /**
     * 获得国家列表
     * @param array $params
     * @return Collection
     */
    public function getCountryList($params = []): Collection
    {
        $query = Country::query()->with('areas', 'areas.areas', 'areas.areas.areas');

        if ($this->formData['keyword'] ?? null) {
            $query->where('cn_name', 'like', "%{$this->formData['keyword']}%")
                  ->orWhere('en_name', 'like', "%{$this->formData['keyword']}%");
        }
        if ($params['country_ids'] ?? []) {
            $query->whereIn('id', $params['country_ids']);
        }

        return $query->orderBy('index')->get();
    }

    /**
     * 获得国家列表
     * @return Collection
     */
    public function getEnabledCountryList(): Collection
    {
        return Country::query()
            ->orderBy('index')
            ->where('enabled', 1)
            ->with('areas', 'areas.areas', 'areas.areas.areas')
            ->get();
    }

    /**
     * 获得国家列表
     * @return Collection
     */
    public function getEnabledSimpleCountryList(): Collection
    {
        return Country::query()
            ->orderBy('index')
            ->where('enabled', 1)
            ->select(['id', 'name', 'code'])
            ->get();
    }

    /**
     * 获得国家列表
     * @param int $expressLineId
     * @return Collection
     */
    public function getEnabledCountryListByExpressLine(int $expressLineId): Collection
    {
        /** @var ExpressLineModel $epl */
        $epl = ExpressLineModel::query()->findOrFail($expressLineId);
        $ids = $epl->warehouses()
            ->with('countries')
            ->get()
            ->pluck('countries')
            ->flatten()->pluck('id')->all();

        return Country::query()
            ->orderBy('index')
            ->whereKey($ids)
            ->with('areas', 'areas.areas', 'areas.areas.areas')
            ->get();
    }

    /**
     * @param  string  $needle
     * @return Collection
     */
    public function searchCountry(string $needle): Collection
    {
        $query = CountryLocale::with('country:id,name')
            ->select(['name', 'full_name'])
            ->selectRaw('country_id as id');

        if ($needle) {
            $query->where('full_name', 'like', "%${needle}%")->orWhere('name', 'like', "%${needle}%");
        }

        if (isset($this->formData['locale']) && $this->formData['locale']) {
            $query->where('locale', $this->formData['locale']);
        }

        return $query->get();
    }

    /**
     * 添加国家
     * @param  int  $countryId
     * @return bool
     * @throws Throwable
     */
    public function addCountry(int $countryId): bool
    {
        /** @var \Khsing\World\Models\Country $country */
        $country = \Khsing\World\Models\Country::findOrFail($countryId);

        //国家已经被添加
        if ($localCountry = Country::query()->where('code', $country->code)->first()) {
            //更新俄语国家名称
            $localCountry->setTranslation('name', Language::RUSSIAN, Country::getRuName($localCountry->cn_name));
            $localCountry->setTranslation('name', Language::ARABIC, Country::getArName($localCountry->cn_name));
            $localCountry->setTranslation('name', Language::PORTUGAL, Country::getArName($localCountry->cn_name));
            $localCountry->setTranslation('name', Language::VIETNAM, Country::getArName($localCountry->cn_name));
            $localCountry->save();

            return true;
        }

        $code = $this->makeCountryCodeStartWithZero($country->callingcode ?? '01');

        $newCountry = new Country([
            'cn_name' => $country->local_name,
            'en_name' => $country->name,
            'timezone' => $code,
            'code' => $country->code,
        ]);
        //设置多语言名称
        $newCountry->setTranslation('name', Language::CHINESE, $country->local_name);
        $newCountry->setTranslation('name', Language::ENGLISH, $country->name);
        // $newCountry->setTranslation('name', 'zh_TW', (new C2TTranslate())->c2t($country->local_name));
        $newCountry->setTranslation('name', Language::RUSSIAN, Country::getRuName($country->local_name));
        $newCountry->setTranslation('name', Language::ARABIC, Country::getArName($country->local_name));
        $newCountry->setTranslation('name', Language::PORTUGAL, Country::getArName($country->local_name));
        $newCountry->setTranslation('name', Language::VIETNAM, Country::getArName($country->local_name));
        //国际区号有可能相同
        if (Timezone::where('timezone', $code)->count()) {
            $newCountry->save();

            return $this->updateCountryAreas($newCountry);
        }

        $timezone = new Timezone(
            [
                'timezone' => $this->makeCountryCodeStartWithZero($country->callingcode ?? '01'),
            ]
        );

        $newCountry->save() && $timezone->save();

        return $this->updateCountryAreas($newCountry);
    }

    /**
     * @param  Country  $country
     * @return bool
     */
    public function updateCountryAreas(Country $country)
    {
        Artisan::queue(
            UpdateCountryArea::class,
            ['code' => $country->code]
        );

        return true;
    }

    /**
     * @return bool
     */
    public static function rememberCountryData()
    {
        return Cache::forever('COUNTRY_CODE_DATA', Storage::disk('local')->get('country-code.json'));
    }

    /**
     * 导出excel
     *
     * @param  Collection  $expressLines
     * @return string
     */
    public function exportExcel(Collection $expressLines)
    {
        $fileName = 'ExpressLines_' . Carbon::now()->format('YmdHis') . '_' . Str::random(6);

        $filePath = "/excel/express-lines/{$fileName}.xlsx";

        Excel::store(new ExpressLineExport($expressLines), admin_path($filePath));

        return secure_asset(Storage::disk('admin_public')->url($filePath));
    }

    /**
     * 批量导出
     *
     * @param  array  $ids
     * @return string
     */
    public function batchExportExcel(array $ids): string
    {
        $data = collect();

        ExpressLineModel::query()
            ->where('is_hidden', 0)
            ->whereIn('id', $ids)
            ->with('countries')
            ->chunk(50, function ($line) use (&$data) {
                $data = $data->concat($line);
            });

        return $this->exportExcel($data);
    }

    /**
     * 全部导出
     *
     * @return string
     */
    public function allExportExcel()
    {
        $data = collect();

        ExpressLineModel::query()->where('is_hidden', 0)
            ->with('countries')->chunk(50, function ($line) use (&$data) {
            $data = $data->concat(collect($line));
        });

        return $this->exportExcel($data);
    }

    /**
     * @param int $expressLineId
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function updateExtraRemarkInfo(int $expressLineId, array $data)
    {
        validator($data, $this->extraRemarkRules())->validate();

        /** @var ExpressLineModel $line */
        $line = ExpressLineModel::findOrFail($expressLineId);

        return $line->update([
            'extra_remark_enabled' => $data['extra_remark_enabled'],
            'extra_remark_name' => $data['extra_remark_name'] ?? '',
            'extra_remark_instruction' => $data['extra_remark_instruction'] ?? '',
        ]);
    }

    /**
     * @param int $expressLineId
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function updateGroupConfig(int $expressLineId, array $data)
    {
        validator($data, $this->groupConfigRules())->validate();

        /** @var ExpressLineModel $line */
        $line = ExpressLineModel::findOrFail($expressLineId);

        $line->groupConfig()->updateOrCreate(
            [
                'express_line_id' => $expressLineId,
            ],
            [
                'is_group' => $data['is_group'] ?? 0,
                'group_raise_threshold' => ($data['group_raise_threshold'] ?? 0) * 1000,
            ]
        );

        return true;
    }

    /**
     * @param int $expressLineId
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getGroupConfig(int $expressLineId)
    {
        /** @var ExpressLineModel $line */
        $line = ExpressLineModel::findOrFail($expressLineId);

        return $line->groupConfig()->firstOrCreate(
            [
                'express_line_id' => $expressLineId,
            ],
            [
                'is_group' => 0,
                'group_raise_threshold' => 0,
            ]
        );
    }

    /**
     * 复制一个现有的线路
     *
     * @param int $id
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function copy(int $id, array $data)
    {
        validator($data, ['name' => 'required|string|max:50'])->validate();

        /** @var ExpressLineModel $line */
        $line = $this->model::query()->findOrFail($id);

        $lineData = $line->toArray();

        Arr::forget($lineData, ['id', 'name']);

        $lineData['name'] = $data['name'];
        $lineData['cn_name'] = $data['name'];
        $lineData['en_name'] = $data['name'];

        return DB::transaction(function () use ($line, $lineData) {
            $new = new ExpressLineModel($lineData);

            $new->save();

            $new->warehouses()->attach($line->warehouses->modelKeys());
            $new->props()->attach($line->props->modelKeys());
            $new->labels()->attach($line->labels->modelKeys());
            //关联渠道
            $regions = $new->regions()->createMany($line->regions->map(function ($item) {
                unset($item['id']);

                return $item->toBaseArray();
            })->all());
            //关联价格规则
            $new->priceRules()->createMany($line->priceRules->map(function ($item) {
                unset($item['id']);

                return $item->toBaseArray();
            })->all());

            /** @var ExpressLineRule $rule */
            if($line->rules) {
                foreach ($line->rules as $rule) {
                    $conditions = $rule->conditions;
                    $regionIds = $rule->regions()->get()->modelKeys();
                    $oldRule = clone $rule;
                    $rule = $rule->toBaseArray();
                    $oldRule = $oldRule->load('regions');

                    $rule['express_line_id'] = $new->getKey();
                    unset($rule['conditions']);

                    unset($rule['id'], $rule['express_line_id']);
                    /** @var ExpressLineRule $newRule */
                    $newRule = $new->rules()->create($rule);

                    $newRule->conditions()->createMany($conditions->map(function ($item) use ($new) {
                        unset($item['id']);

                        $item['express_line_id'] = $new['id'];

                        return $item->toBaseArray();
                    }));
                    //关联下分区
                    $ids = $regions->filter(function ($region) use ($oldRule) {
                        return in_array($region->name,  $oldRule->regions->map(fn ($region) => $region->name)->all());
                    })->modelKeys();

                    $newRule->regions()->attach($ids);
                }
            }


            //关联渠道规则
            $services = $new->services()->createMany($line->services()->get()->map(function ($item) use ($new) {
                unset($item['id']);

                $item['express_line_id'] = $new['id'];

                return $item->toBaseArray();
            })->all());

            $map = [];
            foreach ($line->services as $service) {
                $id = $services->first(fn ($v) => $v['name'] === $service->name)->id;

                $map[$service['id']] = $id;
            }

            $regions->each(function (ExpressLineRegion $region) use ($new, $line, $services, $map) {
                $region->areas()->createMany($line->regions()->get()->first(fn ($v) => $v['name'] === $region->name)
                    ->areas
                    ->map(function ($item) use ($new) {
                        unset($item['id'], $item['region_id']);
                        $item['express_line_id'] = $new['id'];

                        return $item->toBaseArray();
                    })->all());
                // 复制下邮编分区
                $region->postcodeAreas()->createMany($line->regions()->get()->first(fn($v) => $v['name'] === $region->name)
                    ->postcodeAreas
                    ->map(function ($item) use ($new) {
                        unset($item['id'], $item['region_id']);
                        //$item['express_line_id'] = $new['id'];

                        return $item->toBaseArray();
                    })->all());

                $line->regions()->get()->each(function ($oldRegion) use ($region) {
                    if ($region->name === $oldRegion->name) {
                        $prices = $oldRegion->prices->map(function ($p) use ($region) {
                            unset($p['id'], $p['region_id']);
                            $p['express_line_id'] = $region['express_line_id'];

                            return $p->toBaseArray();
                        })->all();

                        $region->prices()->createMany($prices);
                    }
                });

                $line->regions()->get()->each(function ($oldRegion) use ($region, $services, $map) {
                    if ($region->name === $oldRegion->name) {
                        $s = $oldRegion->servicePrices->map(function ($sp) use ($services, $region, $map) {
                            unset($sp['id'], $sp['region_id']);
                            $sp['express_line_id'] = $region['express_line_id'];
                            $sp['service_id'] = $map[$sp->service_id];

                            return $sp->toBaseArray();
                        })->all();

                        $region->servicePrices()->createMany($s);
                    }
                });
            });

            return true;
        });
    }

    /**
     * 删除
     * 本质上是隐藏
     *
     * @param int $id
     * @return bool
     * @throws Exception
     */
    public function destroy(int $id)
    {
        return DB::transaction(function () use ($id) {
            /** @var ExpressLineModel $expressLine */
            $expressLine = ExpressLineModel::query()
                ->where('is_hidden', 0)
                ->findOrFail($id);

            ExpressLineModel::query()->where('id', $id)->delete();

            $incompleteOrderCount = Order::query()
                ->where('express_line_id', $expressLine->getKey())
                ->where('order_status', '<', Order::STATUS_SHIPPED)
                ->count();

            if ($incompleteOrderCount) {
                throw new AccidentException('操作失败,该路线有未完成订单', Code::OPERATE_FAIL);
            }

            $expressLine->warehouses()->detach();
            $expressLine->selfPickupStations()->detach();

            return $expressLine->update(['is_hidden' => 1]);
        });
    }

    /**
     *
     */
    protected function search(): void
    {
        if ($this->formData['keyword'] ?? '') {
            $keyword = $this->formData['keyword'];

            $this->query->where(function ($query) use ($keyword) {
                $query->where('cn_name', 'like', "%{$keyword}%")
                      ->orWhere('en_name', 'like', "%{$keyword}%");
            });
        }

        if ($this->formData['reference_time'] ?? '') {
            $referenceTime = $this->formData['reference_time'];

            $this->query->whereHas('regions', function ($query) use ($referenceTime) {
                $query->where('reference_time->zh_CN', 'like', "%{$referenceTime}%");
            });
        }

        if ($this->formData['country_id'] ?? '') {
            $countryId = $this->formData['country_id'];

            $this->query->whereHas('regions.areas', function ($query) use ($countryId) {
                $query->where('country_id', $countryId);
            });
        }

        if ($this->formData['prop_id'] ?? '') {
            $propId = $this->formData['prop_id'];

            $this->query->whereHas('props', function ($query) use ($propId) {
                $query->where('prop_id', $propId);
            });
        }

        if ($this->formData['express_company_id'] ?? '') {
            $expressCompanyId = $this->formData['express_company_id'];

            $this->query->where('express_company_id', $expressCompanyId);
        }

//        if ($this->formData['warehouse_id'] ?? '') {
//            $warehouseId = $this->formData['warehouse_id'];
//
//            $this->query->whereHas('warehouses', function ($query) use ($warehouseId) {
//                $query->where('warehouse_id', $warehouseId);
//            });
//        }

    }

    /**
     * 更新价格档模式
     *
     * @param ExpressLineModel $expressLine
     * @param array $data
     * @throws Exception
     */
    protected function updatePriceGrade(ExpressLineModel $expressLine, array $data)
    {
        //纯首重续重模式不需要
        if ((int) $expressLine->mode === ExpressLineModel::MODE_1) {
            return;
        }

        $grades = collect($data['price_grade'])->sortBy(fn ($value) => $value['start']);

        $range = $grades->map(fn ($value) => [$value['start'], $value['end']])->flatten()->values();

        for ($i = 1; $i < $range->count() - 1; $i += 2) {
            if ($range[$i] != $range[$i + 1]) {
                throw new AccidentException('需要闭合的价格区间', Code::OPERATE_FAIL);
            }
        }

        $grades->transform(function ($value) {
            return [
                'start' => $value['start'] * 1000,
                'end' => $value['end'] * 1000,
                'cost_price' => $value['cost_price'] * 100,
                'sale_price' => $value['sale_price'] * 100,
            ];
        });

        $expressLine->priceGrade()->delete();

        $expressLine->priceGrade()->createMany($grades);

        $expressLine->update(['min_weight' => $range->first() * 1000, 'max_weight' => $range->last() * 1000]);
        //未设置是保持和最小重量同步
        if (! $expressLine['multi_box_min_weight']) {
            $expressLine->update(['multi_box_min_weight' => $expressLine['min_weight']]);
        }
    }

    /**
     * 更新线路高级设置
     *
     * @param int $id
     * @param array $data
     * @return bool
     * @throws Throwable
     */
    public function updateAdvanceSetting(int $id, array $data): bool
    {
        $data = validator($data, $this->advanceSettingRules())->validate();

        /** @var ExpressLineModel $line */
        $line = ExpressLineModel::query()->findOrFail($id);

        if ($data['should_auto_delivery'] ?? 0) {
            $payOnDelivery = PayOnDeliveryConfig::query()->first();

            throw_unless(
                $payOnDelivery && $payOnDelivery->status,
                new AccidentException('系统未开启货到付款支付，无法开启打包自动生成货到付款订单', Code::OPERATE_FAIL)
            );
        }
        //检查前置条件
        if ($data['should_auto_delivery'] ?? 0) {
            throw_if(
                ($data['is_delivery'] ?? 0) === 0
                || ($data['default_pickup_station_id'] ?? null) === null,
                new AccidentException('自动打包成订单需要设置为自提线路功能并且设置自提点', Code::OPERATE_FAIL)
            );
        }

        return $line->update([
                'is_delivery' => $data['is_delivery'] ?? 0,
                'default_pickup_station_id' => $data['default_pickup_station_id'] ?? null,
                'should_auto_delivery' => $data['should_auto_delivery'] ?? 0,
                'express_company_id' => 0,
                'docking_type' => ($data['express_company_id'] ?? 0) ?: 0,
                'order_mode' => $data['order_mode'] ?? 0,
        ]) !== false;
    }

    public function getChannelCodeList($dockingType)
    {
        if ($dockingType == OrderDockingRecord::TYPE_LT_EXP) {
            $data = (new LTExp())->channels();

            return collect($data)->map(function ($channel) {
                return [
                    'name' => $channel['cnname'],
                    'code' => $channel['code'],
                ];
            })->all();
        }

        if ($dockingType == OrderDockingRecord::TYPE_XZH_TMS) {
            return (new \App\Services\ThirdPart\XzhTMS\XzhTMS())->services();
        }

        if ($dockingType == OrderDockingRecord::TYPE_K5) {
            return (new \App\Services\ThirdPart\K5\K5())->channels();
        }
        if ($dockingType == OrderDockingRecord::TYPE_RUI_YUN) {
            return (new \App\Services\ThirdPart\RuiYun\RuiYun())->channels();
        }
        if ($dockingType == OrderDockingRecord::TYPE_HUA_LEI_NEW) {
            return (new \App\Services\ThirdPart\HuaLeiDeRun\HuaLeiDeRun())->productList();
        }
        if ($dockingType == OrderDockingRecord::TYPE_JIN_BANG) {
            return (new \App\Services\ThirdPart\JinBang\JinBang())->customerChannels();
        }
        if ($dockingType == OrderDockingRecord::TYPE_K5_STAR) {
            return (new \App\Services\ThirdPart\K5Star\K5Star())->channels();
        }
        if ($dockingType == OrderDockingRecord::TYPE_K5_YUN_TU) {
            return (new \App\Services\ThirdPart\K5YunTu\K5YunTu())->channels();
        }
        if ($dockingType == OrderDockingRecord::TYPE_K5_HH) {
            return (new \App\Services\ThirdPart\K5HanHan\K5HanHan())->channels();
        }
        if ($dockingType == OrderDockingRecord::TYPE_SHENG_YUN) {
            return (new \App\Services\ThirdPart\ShengYun\ShengYun())->channels();
        }
        if ($dockingType == OrderDockingRecord::TYPE_YUN_TU_LOGISTICS) {
            return (new \App\Services\ThirdPart\YunTuLogistics\YunTuLogistics())->channels();
        }
        if ($dockingType == OrderDockingRecord::TYPE_EMS_YI_CHANG) {
            return [ // 仅支持006和034
                ['code' => '006', 'name' => '国际EMS物品'],
                ['code' => '034', 'name' => 'e包裹特惠'],
            ];
        }
        if ($dockingType == OrderDockingRecord::TYPE_BA_SHI) {
            return [ // 仅支持006和034
                ['code' => 'THKEC-ALL', 'name' => 'KEC全程服务'],
                ['code' => 'THKEC-LM', 'name' => 'KEC仅尾程'],
                ['code' => 'THFLASH', 'name' => 'FLASH仅尾程'],
            ];
        }

        return ThirdPartyChannel::query()->where('docking_type',$dockingType)->get()->all();
    }

    /**
     * 更新线路高级设置
     *
     * @param int $id
     * @param array $data
     * @return bool
     * @throws Throwable
     */
    public function updateDockingSetting(int $id, array $data): bool
    {
        $data = validator($data, $this->dockingSettingRules())->validate();

        /** @var ExpressLineModel $line */
        $line = ExpressLineModel::query()->findOrFail($id);

        DB::transaction(function () use ($line, $data) {
            $line->update([
                'docking_type' => ($data['docking_type'] ?? 0) ?: 0,
                'channel_code' => $data['channel_code'] ?? '',
                'push_type' => $data['push_type'] ?? 1,
                'third_push_now' => $data['third_push_now'] ?? 0,
                'channel_type' => $data['channel_type'] ?? ExpressLineModel::CHANNEL_TYPE_SINGLE,
                'auto_sn_express_id' => $data['auto_sn_express_id'] ?? 0,
                'auto_sn_mode' => $data['auto_sn_mode'] ?? 0,
            ]);

            if (($data['docking_mode'] ?? null) == 1) {
                $line->update([
                    'auto_sn_express_id' => 0,
                    'auto_sn_mode' => 0,
                ]);
            } elseif (($data['docking_mode'] ?? null) == 2) {
                $line->update([
                    'docking_type' => 0,
                    'channel_code' => '',
                ]);
            }

            // if (($data['docking_enabled'] ?? 1) == 0) {
            //     $line->update([
            //         'auto_sn_express_id' => 0,
            //         'docking_type' => 0,
            //         'channel_code' => '',
            //     ]);
            // }
        });

        return true;
    }

    /**
     * @param int $expressLineId
     * @return Collection
     */
    public function usableSelfPickupStation(int $expressLineId)
    {
        return ExpressLineModel::query()->findOrFail($expressLineId)->selfPickupStations;
    }

    /**
     * @return array|array[]
     */
    public function getDockingTypes()
    {
        return OrderDockingType::all()->map(function ($item) {
            return [
                'id' => $item['type'],
                'name' => $item['name'],
            ];
        })->all();
    }

    /**
     * 设置状态
     *
     * @param  int  $id
     * @param  bool  $status
     * @return bool
     */
    public function setRecommend(int $id, bool $status): bool
    {
        $setting = $this->model::findOrFail($id);

        return $setting->update(['is_great_value' => (int) $status]);
    }

    protected function fillData(array $data)
    {
        return [
            'cn_name' => $data['name'],
            'en_name' => $data['name'],
            'name' => $data['name'],
            'icon_id' => $data['icon'] ?? 0,
            'is_great_value' => $data['is_great_value'],
            'reference_time' => $data['reference_time'],
            'first_weight' => ($data['first_weight'] ?? 0) * 1000,
            'first_money' => ($data['first_money'] ?? 0) * 100,
            'next_weight' => ($data['next_weight'] ?? 0) * 1000,
            'next_money' => ($data['next_money'] ?? 0) * 100,
            'min_weight' => (int) $data['mode'] === ExpressLine::MODE_1
                ? $data['min_weight'] * 1000
                : 0,
            'max_weight' =>  (int) $data['mode'] === ExpressLine::MODE_1
                ? $data['max_weight'] * 1000
                : 0,
            'factor' => $data['factor'] ?: 1,
            'remark' => $data['remark'],
            'has_factor' => $data['has_factor'],
            'need_clearance_code' => $data['need_clearance_code'] ?? 0,
            'clearance_code_remark' => $data['clearance_code_remark'] ?? '',
            'need_personal_code' => $data['need_personal_code'] ?? 0,
            'need_id_card' => $data['need_id_card'] ?? 0,
            'first_cost_money' => ($data['first_cost_money'] ?? 0) * 100,
            'next_cost_money' => ($data['next_cost_money'] ?? 0) * 100,
            'mode' => $data['mode'] ?? 1,
            'ceil_weight' => $data['ceil_weight'] ?? 0,
            'weight_rise' => $data['weight_rise'] ?? 0,
            'multi_boxes' => $data['multi_boxes'] ?? 0,
            'multi_boxes_ceil' => $data['multi_boxes_ceil'] ?? 0,
            'is_unique' => $data['is_unique'] ?? 0,
            'is_avg_weight' => $data['is_avg_weight'] ?? 0,
            'multi_box_min_weight' => (($data['multi_box_min_weight'] ?? 0) ?: 0) * 1000,
            'no_throw_condition'=>$data['no_throw_condition'] ?? null
        ];
    }

    /**
     * 线路国家只能是仓库所支持的
     *
     * @param  array  $warehouseIds
     * @param  array  $countryIds
     * @return bool
     */
    protected function onlyHasWarehousesCountries(array $warehouseIds, array $countryIds): bool
    {
        $countriesCount = count(array_unique($countryIds));

        return $this->getWarehouseCountryList($warehouseIds)
            ->filter(function ($value) use ($countryIds) {
                return in_array($value->id, $countryIds);
            })->count() === $countriesCount;
    }

    /**
     * @param  string  $code
     * @return mixed|string
     */
    public function makeCountryCodeStartWithZero(string $code)
    {
        $code = '000' . $code;

        if (mb_strlen($code) > 4) {
            return mb_substr($code, -4);
        }

        return $code;
    }

    public function showAuth($id)
    {
        return $this->query->with(['authUserGroups', 'authMemberLevels', 'authUsers', 'authUserTags'])->findOrFail($id);
    }

    public function updateAuth($id, $data)
    {
        validator($data,$this->userAuthRules())->validate();

        $expressLine = ExpressLineModel::query()->findOrFail($id);

        return DB::transaction(function () use ($expressLine, $data){

            $expressLine->update(['auth_target' => $data['auth_target']]);

            if($data['auth_target'] == ExpressLineModel::AUTH_ALL) return true;

            $expressLine->authUserGroups()->sync($data['user_group_ids'] ?? []);

            $expressLine->authMemberLevels()->sync($data['member_level_ids'] ?? []);

            $expressLine->authUserTags()->sync($data['tag_ids'] ?? []);

            $expressLine->authUsers()->sync($data['user_ids'] ?? []);

            return true;
        });
    }

    public function userAuthRules()
    {
        return [
            'auth_target' => 'required|int|in:1,2,3',
            'user_group_ids' => 'nullable|array',
            'user_group_ids.*' => 'nullable|int',
            'member_level_ids' => 'nullable|array',
            'member_level_ids.*' => 'nullable|int',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'nullable|int',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'nullable|int',
        ];

    }

    protected function translateRules()
    {
        return [
            'language' => 'required|string',
            'name' => 'string|required|max:80',
            'remark' => 'required|string|max:10240',
        ];
    }

    protected function advanceSettingRules()
    {
        return [
            'is_delivery' => 'sometimes|nullable|in:0,1,2',
            'default_pickup_station_id' => 'required_if:is_delivery,1,2|nullable|integer',
            'should_auto_delivery' => 'sometimes|nullable|in:0,1',
            'express_company_id' => 'sometimes|nullable',
            'order_mode' => 'sometimes|nullable|in:0,1,2',
        ];
    }

    protected function dockingSettingRules()
    {
        return [
            'docking_type' => 'sometimes|nullable|integer',
            'channel_code' => 'nullable|string|max:50',
            'push_type' => 'nullable|int|int:1,2',
            'third_push_now' => 'nullable|int|int:0,1',
            'channel_type' => 'sometimes|nullable|int|in:1,2',
            'auto_sn_express_id' => 'sometimes|nullable|int',
            'auto_sn_mode' => 'sometimes|nullable|int',
            'docking_enabled' => 'sometimes|nullable|bool',
            'docking_mode' => 'sometimes|nullable|integer',
        ];
    }

    private function extraRemarkRules()
    {
        return [
            'extra_remark_enabled' => 'required|in:0,1',
            'extra_remark_name' => 'sometimes|nullable|string|max:50',
            'extra_remark_instruction' => 'sometimes|nullable|string|max:180',
        ];
    }

    private function groupConfigRules()
    {
        return [
            'is_group' => 'required|in:0,1',
            'group_raise_threshold' => 'required|gte:0',
            //'group_raise_factor' => 'sometimes|nullable|string|max:180',
        ];
    }

    /**
     * 表单规则
     * @return array
     */
    private function rules()
    {
        return [
            'name' => 'required|string|max:50',
            'warehouses' => 'required|array',
            'warehouses.*' => 'required|integer',
            'countries' => 'required|array',
            'countries.*' => 'required|integer|gt:0',
            'types' => 'required|array',
            'types.*' => 'required|integer|gt:0',
            'labels' => 'nullable|array',
            'labels.*' => 'nullable|integer|gt:0',
            'icon' => 'required|integer',
            'reference_time' => 'required|string|max:15',
            'first_weight' => 'required_if:mode,1|nullable|numeric|gte:0',
            'first_money' => 'required_if:mode,1|nullable|numeric|gte:0',
            'next_weight' => 'required_if:mode,1|nullable|numeric|gte:0',
            'next_money' => 'required_if:mode,1|nullable|numeric|gte:0',
            'min_weight' => 'required_if:mode,1|nullable|numeric|gte:0',
            'max_weight' => 'required_if:mode,1|nullable|numeric|gt:0|gte:min_weight|max:1000000000',
            'factor' => 'required|numeric|gt:0',
            'remark' => 'required|string|max:1024',
            'is_great_value' => 'required|integer|in:0,1',
            'has_factor' => 'required|in:0,1',
            'need_clearance_code' => 'required|in:0,1',
            'clearance_code_remark' => 'sometimes|nullable|string|max:180',
            'need_id_card' => 'nullable|in:0,1',
            'need_personal_code' => 'nullable|in:0,1',
            'need_passport_code' => 'nullable|in:0,1',
            'is_unique' => 'nullable|in:0,1',
            'first_cost_money' => 'sometimes|nullable|numeric|gte:0',
            'next_cost_money' => 'sometimes|nullable|numeric|gte:0',
            'ceil_weight' => 'sometimes|nullable|in:0,1',
            'weight_rise' => 'sometimes|nullable|in:0,0.5,1',
            'multi_boxes' => 'required|in:0,1,2,3',
            'multi_boxes_ceil' => 'sometimes|nullable|in:0,0.5,1',
            'multi_box_min_weight' => 'sometimes|nullable|gte:0',
            'mode' => 'required|in:1,2,3',
            'is_avg_weight' => 'sometimes|nullable|in:0,1',
            'price_grade' => 'required_if:mode,2,3|array|min:1',
            'price_grade.*.start' => 'required|numeric|gte:0|max:1000000000',
            'price_grade.*.end' => 'required|numeric|gt:0|max:1000000000',
            'price_grade.*.cost_price' => 'gte:0|numeric',
            'price_grade.*.sale_price' => 'required|gte:0|numeric',
            'no_throw_condition' => 'nullable|array',
            'no_throw_condition.type' => 'nullable|in:1,2,3,4',
            'no_throw_condition.condition' => 'nullable|in:<,<=,>,>=',
            'no_throw_condition.value' => 'nullable|int',
            'no_throw_condition.checked' => 'nullable|in:0,1',
        ];
    }

    private function costRules()
    {
        return [
            '*' => 'nullable|array',
            '*.id' => 'required',
            '*.type' => 'required',
            '*.price' => 'required|numeric',
        ];
    }

    /**
     * 获取某个字段数据
     *
     * @param  string  $column
     * @param  int  $limit
     * @return Collection
     */
    public function getColumnData(string $column, int $limit = 5)
    {
        if (in_array($column, $this->model->searchable)) {
            if (mb_strpos($column, '.')) {
                [$relation, $column] = explode('.', $column);
                //可能需要换个名字
                if (array_key_exists($relation, static::$relation)) {
                    $relation = static::$relation[$relation];
                }

                $query = $this->model::with($relation . ':' . $column)
                    ->where('is_hidden', 0)
                    ->get()
                    ->pluck($relation)
                    ->flatten()
                    ->unique(function ($model) use ($column) {
                        return $model->$column;
                    });
            } else {
                $query = $this->model::select([$this->model->getQualifiedKeyName(), $column])
                    ->where('is_hidden', 0)
                    ->get()
                    ->unique(function ($model) use ($column) {
                        return $model->$column;
                    });
            }
            //数值转换，可能需要更加优雅的方式解决
            return $query->map(function ($model) use ($column) {
                if (array_key_exists($column, static::$casts)) {
                    return [$column => $model->$column / static::$casts[$column]];
                }

                return [$column => $model->$column];
            })->sortBy(function ($value) use ($column) {
                return $value[$column];
            })->values();
        }

        return collect([]);
    }

    /**
     * 设置状态
     *
     * @param int $id
     * @param bool $status
     * @return bool
     */
    public function setStatuss(int $id, bool $status): bool
    {
        $setting = $this->model::query()->findOrFail($id);

        return $setting->update(['enabled' => (int) $status]);
    }

    /**
     * 更新排序
     */
    public function updateSort()
    {
        validator($this->formData, [
            'id' => 'required|integer',
            'sort' => 'required|integer',
        ])->validate();

        $expressLine = $this->model::query()->findOrFail($this->formData['id']);

        throw_if(
            $this->model::query()->where('sort', $this->formData['sort'])->whereNot('id', $this->formData['id'])->exists(),
            new AccidentException('排序不能重复，请重新设置', Code::OPERATE_FAIL)
        );

        return $expressLine->update(['sort' => $this->formData['sort']]);
    }

    /**
     * @param UploadedFile $file
     * @return array|true
     * @throws AccidentException
     */
    public function import(UploadedFile $file)
    {
        // 0表示无限制
        set_time_limit(0);

        $dataList = $this->parseData($file);

        DB::beginTransaction();
        try {
            $saveError = [];
            $expressLine = $this->model::where('enabled', 1)->select(['id','myLogisticsId','channel_code'])
                ->get()->mapWithKeys(function ($item) {
                    $key = $item->channel_code . '-' . $item->myLogisticsId;
                    return [$key => $item->id];
                })->toArray();
            foreach ($dataList as $key => $item) {
                $expressId = $expressLine[$item[3].'-'.$item[1]] ?? 0;
                try {
                    if ($expressId) {
                        $this->update($expressId, $item);
                    } else {
                        $this->create($item);
                    }
                } catch (Exception $e) {
                    $saveError[] = "{$key}：{$e->getMessage()}";
                }
                if ($saveError) return $saveError;
                DB::commit();
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            info('导入失败', ['message' => $th->getMessage()]);
            throw new AccidentException('导入失败，请检查Excel数据格式：'.$th->getMessage(), Code::OPERATE_FAIL);
        }
        return true;
    }

    public function export()
    {
        $expressLine = $this->model::where('enabled', 1)->get();
        var_dump($expressLine->toArray());
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

            $excel = (new \Vtiful\Kernel\Excel($config))
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

    /**
     * @param $array
     * @return bool
     */
    protected function areAllArrayElementsEmpty($array): bool
    {
        $array = array_filter(array_unique($array));
        if (empty($array)) {
            return true; // 所有元素都为空，返回true
        }
        return false; // 发现非空元素，返回false
    }

    /**
     * 导入更新
     * @param $id
     * @param $data
     * @return mixed
     */
    public function update($id, $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $names = explode(',', $data[5]);
            $propIds = PackageProp::query()
                ->where(function ($query) use ($names) {
                    foreach ($names as $name) {
                        $query->orWhere('name->zh_CN', $name);
                    }
                })->pluck('id')->toArray();
            /** @var ExpressLineModel $el */
            $el = ExpressLineModel::query()->findOrFail($id);
            $el->setTranslation('name', Language::CHINESE, $data[2]);
            $company_id = CompanyExpressModel::query()
                ->where('name', $data[0])->value('id');

            $channel_name = LogisticsChannelModel::query()
                ->where(['express_companies_id' => $company_id, 'code' => $data[3],
                    'enable' => 1])->value('name');
            $updateData = [
                'myLogisticsId' => $data[1],
                'myLogisticsChannelId' => $data[4],
                'express_company_id' => $company_id,
                'express_company_name' => $data[0],
                'channel_code' => $data[3],
                'cn_name' => $channel_name ?? '',
                'min_weight' => $data[7] * 1000,
                'base_mode' => 0,
            ];
            $el->update($updateData);
            $el->props()->sync(collect($propIds)->unique());

            return true;
        });
    }

    /**
     * @param $data
     * @return mixed
     * @throws Throwable
     * @throws ValidationException
     */
    public function create($data)
    {
        $names = explode(',', $data[5]);
        $propIds = PackageProp::query()
            ->where(function ($query) use ($names) {
                foreach ($names as $name) {
                    $query->orWhere('name->zh_CN', $name);
                }
            })->pluck('id')->toArray();

        $company_id = CompanyExpressModel::query()
            ->where('name', $data[0])->value('id');

        $channel_name = LogisticsChannelModel::query()
            ->where(['express_companies_id' => $company_id, 'code' => $data[3],
                'enable' => 1])->value('name');

        $newData = [
            'myLogisticsId' => $data[1],//马帮物流商ID
            'myLogisticsChannelId' => $data[4],//马帮物流渠道ID
            'express_company_id' => $company_id,//对接快递物流公司ID
            'express_company_name' => $data[0],//物流商名称
            'channel_code' => $data[3],//渠道代码
            'name' => $channel_name ?? '',//线路名称
            'cn_name' => $channel_name ?? '',//线路中文名
            'code' => ExpressLineModel::generateCode(),
            'prop_ids' => $propIds,
            'base_mode' => 0,
            'mode' => 2,
            'min_weight' => $data[7] * 1000,
            'max_weight' => 999 * 1000,
            'ceil_weight' => 1,
        ];

        validator($newData, $this->createRules())->validate();

        throw_unless(PackageProp::isValid($newData['prop_ids']), new Exception('包裹属性错误！', Code::OPERATE_FAIL));

        unset($newData['prop_ids']);
        return DB::transaction(function () use ($propIds, $newData) {
            /** @var ExpressLineModel $epl */
            $epl = ExpressLineModel::query()->create($newData);
            $epl->setTranslation('name', Language::CHINESE, $newData['name']);
            $epl->save();
            $epl->props()->sync(collect($propIds)->unique());

            return true;
        });
    }

    /**
     * @return string[]
     */
    protected function createRules(): array
    {
        return [
            'myLogisticsId' => 'required|int|gt:0',//马帮物流商ID
            'myLogisticsChannelId' => 'required|int|gt:0',//马帮物流渠道ID
            'express_company_id' => 'required|int|gt:0',//物流商ID
            'express_company_name' => 'nullable|string|max:50',//物流商名称
            'channel_code' => 'nullable|string|max:50',//物流渠道编码
            'cn_name' => 'required|max:50',//模板名称
            'prop_ids' => 'required|array',//产品属性
            'base_mode' => 'required|int|in:0,1',//计费模式
            'mode' => 'required|in:1,2,3,4,5',//计费价格模式
            'min_weight' => 'required|numeric|gt:0',//渠道最小重量
            'ceil_weight' => 'sometimes|nullable|in:0,1',//渠道最小重量
        ];
    }

    /**
     * 获取已启用物流模板
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getEnabledTemplatesList()
    {
        $this->query->with(
            [
                'countries',// 联国家数据
                'props',// 关联属性数据
            ]
        )->where('is_hidden', 0);// 只查询未隐藏的线路

        return parent::index();
    }

}
