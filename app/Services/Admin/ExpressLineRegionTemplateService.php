<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:40
 */

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\Country;
use App\Models\CountryArea;
use App\Models\ExpressLineRegionPostcodeArea;
use App\Models\ExpressLineRegionPostcodeAreaModel;
use App\Models\ExpressLineRegionTemplate;
use App\Models\ExpressLineRegionTemplateArea;
use App\Models\RegionTemplate;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Vtiful\Kernel\Excel;
use App\Exceptions\AccidentException;

class ExpressLineRegionTemplateService extends BaseService
{
    use HasNameUniqueValidation;

    protected $filterRules = [
        'name' => ['like', 'keyword']
    ];

    public function __construct()
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = new ExpressLineRegionTemplate();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    /**
     * @return LengthAwarePaginator
     */
    public function groupIndex()
    {
        $this->query = RegionTemplate::query();

        $this->query->withCount('regions');

        return parent::index();
    }

    /**
     * @param $id
     * @return Model
     */
    public function groupShow($id): Model
    {
        return RegionTemplate::query()->findOrFail($id);
    }

    /**
     * @param array $data
     * @return Model
     * @throws ValidationException
     */
    public function groupCreate(array $data): Model
    {
        $data = validator($data, ['name' => 'required|string|max:50'])->validate();

        return RegionTemplate::query()->create($data);
    }

    /**
     * @param $id
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function groupUpdate($id, array $data): bool
    {
        $data = validator($data, ['name' => 'required|string|max:50'])->validate();

        return RegionTemplate::query()->findOrFail($id)->update($data);
    }

    /**
     * @param $id
     * @return bool
     */
    public function groupDelete($id): bool
    {
        return DB::transaction(function () use ($id) {
            /** @var RegionTemplate $rt */
            $rt = RegionTemplate::query()->findOrFail($id);


            $rt->regions->each(function ($region) {
                $region->areas()->delete();
                $region->postcodeAreas()->delete();
            });

            $rt->regions()->delete();

            $rt->delete();

            return true;
        });
    }

    /**
     * @param null $id
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function index($id = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $this->query->withCount('areas', 'postcodeAreas', 'country')
            ->where('template_id', $id);

        if (isset($this->formData['keyword'])) {
            $this->query->where('name', 'like', "%{$this->formData['keyword']}%");
        }

        return parent::index();
    }

    /**
     * @param $id
     * @return Model
     */
    public function show($id): Model
    {
        $data = $this->model::query()
            ->with('areas', 'postcodeAreas')
            ->findOrFail($id);

        $countryIds = $data['areas']->pluck('country_id')->values();
        $areaIds = $data['areas']->pluck('area_id')->values();
        $subAreaIds = $data['areas']->pluck('sub_area_id')->values();

        $partitions = Country::query()->with('areas.areas', function ($q) use ($subAreaIds) {
            $q->whereKey($subAreaIds);
        })->with('areas', function ($q) use ($areaIds) {
            $q->whereKey($areaIds);
        })->whereKey($countryIds)->get();

        $data['partitions'] = $partitions;

        return $data;
    }

    /**
     * 创建分区
     *
     * @param $tId
     * @param array $data
     * @return mixed
     * @throws ValidationException
     */
    public function create($tId, array $data): mixed
    {
        $data = validator($data, $this->rules())->validate();

        return DB::transaction(function () use ($data, $tId) {
            /** @var ExpressLineRegionTemplate $region */
            $region = ExpressLineRegionTemplate::query()->create([
                'name' => $data['name'],
                'reference_time' => $data['reference_time'],
                'template_id' => $tId,
                'type' => $data['type'],
                'country_id' => $data['country_id'] ?? null,
            ]);

            if ($data['type'] == ExpressLineRegionTemplate::TYPE_AREA) {
                foreach ($data['areas'] as $area) {
                    if (ExpressLineRegionTemplateArea::query()
                        ->where('template_id', $tId)
                        ->where('region_id', '!=', $region->id)
                        ->where([
                            ['country_id', '=', $area['country_id']],
                            ['area_id', '=', $area['area_id'] ?? null],
                            ['sub_area_id', '=', $area['sub_area_id'] ?? null],
                        ])
                        ->count()) {
                        throw new AccidentException('分区区域不能和其他分区重复', Code::OPERATE_FAIL);
                    }

                    if (ExpressLineRegionTemplate::query()
                        ->whereKeyNot($region->id)
                        ->where('template_id', $tId)
                        ->whereNotNull('country_id')
                        ->where('country_id', '=', $area['country_id'])
                        ->count()) {
                        throw new AccidentException('分区区域不能和其他分区重复', Code::OPERATE_FAIL);
                    }
                }

                [$countries, $areas] = $this->getCountriesAndAreasData($data);

                $this->mapAreasData($data, $countries, $areas, $region->id, $tId)
                    ->chunk(50)->each(function ($values) use ($region) {
                        $region->areas()->insert($values->all());
                    });
            } elseif ($data['type'] == ExpressLineRegionTemplate::TYPE_POSTCODE) {
                if (ExpressLineRegionTemplate::query()
                    ->whereKeyNot($region->id)
                    ->where('template_id', $tId)
                    ->where('type', ExpressLineRegionTemplate::TYPE_POSTCODE)
                    ->where('country_id', '=', $region['country_id'])
                    ->count()) {
                    throw new AccidentException('分区区域不能和其他分区重复', Code::OPERATE_FAIL);
                }

                ExpressLineRegionTemplate::query()
                    ->where('template_id', $tId)
                    ->whereKeyNot($region->id)
                    ->whereNotNull('country_id')
                    ->where('country_id', '=', $region['country_id'])
                    ->get()->each(function ($r) use ($data) {
                        $areas = $data['postcodes'] ?? [];
                        $tAreas = $r->postcodeAreas;

                        foreach ($areas as $area) {
                            foreach ($tAreas as $t) {
                                if (max($area['start'], $t['start']) <= min($area['end'], $t['end'])) {
                                    throw new AccidentException('邮编范围不能和其他邮编分区重叠', Code::OPERATE_FAIL);
                                }
                            }
                        }
                    });

                $this->verifyPostcodeRanges($data['postcodes'] ?? []);

                $region->update(['type' => ExpressLineRegionTemplate::TYPE_POSTCODE]);

                foreach ($data['postcodes'] as $postcode) {
                    if ($data['country_id'] != ExpressLineRegionPostcodeAreaModel::CANADA_COUNTRY_ID) {
                        preg_match_all('/\d+/', $postcode['start'], $sMatch);
                        preg_match_all('/\d+/', $postcode['end'], $eMatch);

                        $start = collect($sMatch[0])->max();
                        $end = collect($eMatch[0])->max();

                        if ($start > $end) {
                            throw new AccidentException('起始邮编不能大于结束邮编', Code::OPERATE_FAIL);
                        }
                    }

                    $region->postcodeAreas()->create([
                        'template_id' => $tId,
                        'type' => $postcode['type'] ?? ExpressLineRegionPostcodeArea::TYPE_RANGE,
                        'start' => $postcode['start'],
                        'end' => $postcode['end'],
                    ]);
                }
            }

            return true;
        });
    }

    /**
     * 更新线路区域
     *
     * @param $id
     * @param array $data
     * @return mixed
     * @throws ValidationException
     */
    public function update($id, array $data)
    {
        $data = validator($data, $this->rules())->validate();

        return DB::transaction(function () use ($id, $data) {
            /** @var ExpressLineRegionTemplate $region */
            $region = ExpressLineRegionTemplate::query()->findOrFail($id);

            $region->update([
                'name' => $data['name'],
                'reference_time' => $data['reference_time'] ?? '',
                'country_id' => $data['country_id'] ?? null,
                'type' => $data['type'],
            ]);

            if ($data['type'] == ExpressLineRegionTemplate::TYPE_AREA) {
                foreach ($data['areas'] as $area) {
                    if (ExpressLineRegionTemplateArea::query()
                        ->where('template_id', $region->template_id)
                        ->where('region_id', '!=', $region->id)
                        ->where([
                            ['country_id', '=', $area['country_id']],
                            ['area_id', '=', $area['area_id'] ?? null],
                            ['sub_area_id', '=', $area['sub_area_id'] ?? null],
                        ])
                        ->count()) {
                        throw new AccidentException('分区区域不能和其他分区重复', Code::OPERATE_FAIL);
                    }

                    if (ExpressLineRegionTemplate::query()
                        ->where('template_id', $region->template_id)
                        ->whereKeyNot($region->id)
                        ->whereNotNull('country_id')
                        ->where('country_id', '=', $area['country_id'])
                        ->count()) {
                        throw new AccidentException('分区区域不能和其他分区重复', Code::OPERATE_FAIL);
                    }
                }

                [$countries, $areas] = $this->getCountriesAndAreasData($data);

                $region->areas()->delete();
                $region->postcodeAreas()->delete();

                $this->mapAreasData($data, $countries, $areas, $region->id, $region->template_id)
                    ->chunk(50)->each(function ($values) use ($region) {
                        $region->areas()->insert($values->all());
                    });
            } elseif ($data['type'] == ExpressLineRegionTemplate::TYPE_POSTCODE) {
                if (ExpressLineRegionTemplate::query()
                    ->where('template_id', $region->template_id)
                    ->whereKeyNot($region)
                    ->where('type', ExpressLineRegionTemplate::TYPE_POSTCODE)
                    ->where('country_id', '=', $region['country_id'])
                    ->count()) {
                    throw new AccidentException('分区区域不能和其他分区重复', Code::OPERATE_FAIL);
                }

                ExpressLineRegionTemplate::query()
                    ->where('template_id', $region->template_id)
                    ->whereKeyNot($region->id)
                    ->where('country_id', '=', $region['country_id'])
                    ->get()->each(function ($r) use ($data) {
                        $areas = $data['postcodes'] ?? [];
                        $tAreas = $r->postcodeAreas;

                        foreach ($areas as $area) {
                            foreach ($tAreas as $t) {
                                if (max($area['start'], $t['start']) <= min($area['end'], $t['end'])) {
                                    throw new AccidentException('邮编范围不能和其他邮编分区重叠', Code::OPERATE_FAIL);
                                }
                            }
                        }
                    });

                $region->update(['type' => ExpressLineRegionTemplate::TYPE_POSTCODE]);

                $this->verifyPostcodeRanges($data['postcodes'] ?? []);

                $region->areas()->delete();

                $region->areas()->delete();
                $region->postcodeAreas()->delete();

                foreach ($data['postcodes'] as $postcode) {
                    if ($data['country_id'] != ExpressLineRegionPostcodeAreaModel::CANADA_COUNTRY_ID) {
                        preg_match_all('/\d+/', $postcode['start'], $sMatch);
                        preg_match_all('/\d+/', $postcode['end'], $eMatch);

                        $start = collect($sMatch[0])->max();
                        $end = collect($eMatch[0])->max();

                        if ($start > $end) {
                            throw new AccidentException('起始邮编不能大于结束邮编', Code::OPERATE_FAIL);
                        }
                    }

                    $region->postcodeAreas()->create([
                        'type' => $postcode['type'] ?? ExpressLineRegionPostcodeArea::TYPE_RANGE,
                        'start' => $postcode['start'],
                        'end' => $postcode['end'],
                        'template_id' => $region->template_id,
                    ]);
                }
            }

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
            /** @var ExpressLineRegionTemplate $region */
            $region = ExpressLineRegionTemplate::query()->findOrFail($id);

            $region->areas()->delete();
            $region->postcodeAreas()->delete();

            return $region->delete();
        });
    }

    /**
     * 设置状态
     *
     * @param  int  $id
     * @param  bool  $status
     * @return bool
     */
    public function setStatus(int $id, bool $status): bool
    {
        $setting = ExpressLineRegionTemplate::findOrFail($id);

        return $setting->update(
            [
                'enabled' => (int)$status,
            ]
        );
    }

    /**
     * @param array|null $ranges
     * @param int $countryId
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
     * @param  array  $data
     * @return array
     */
    protected function getCountriesAndAreasData(array $data): array
    {
        $countries = Country::query()
            ->whereKey(array_column($data['areas'], 'country_id'))
            ->select('id', 'name')
            ->get();

        $areaIds = collect($data['areas'])->pluck('area_id')
            ->filter(fn ($v) => $v)
            ->all();
        $subAreaIds = collect($data['areas'])->pluck('sub_area_id')->values()
            ->filter(fn ($v) => $v)
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
     * @param int $regionId
     * @param int $tId
     * @return Collection
     */
    protected function mapAreasData(array $data, Collection $countries, Collection $areas, int $regionId, int $tId): Collection
    {
        return collect($data['areas'])->map(function ($item) use ($countries, $areas, $regionId, $tId) {
            $data =  [
                'template_id' => $tId,
                'region_id' => $regionId,
                'country_id' => $item['country_id'],
                'country_name' => json_encode($countries
                    ->firstWhere('id', $item['country_id'])
                    ->getTranslations('name')),
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
     * @param UploadedFile $file
     * @return mixed
     * @throws Exception
     */
    public function parse(UploadedFile $file): mixed
    {
        try {
            $config = ['path' => $file->getPath()];

            $excel = (new Excel($config))
                ->openFile($file->getFilename())
                ->openSheet();

            $items = collect([]);
            while (($row = $excel->nextRow()) !== null) {
                if (empty($row[1])) {
                    break;
                }

                $row[1] = trim($row[1]);

                $items->push(collect($row));
            }

            //删除分区信息表头
            $items->shift();
            $regionData = $items->shift();

            //删除数据信息表头
            $items->shift();

            return $this->format($regionData, $items);
        } catch (Exception $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            info('数据解析失败', [$throwable->getMessage(), $throwable->getTraceAsString()]);
            throw new AccidentException('模板数据异常，请检查模板数据是否填写完整', Code::OPERATE_FAIL);
        }
    }

    protected function format($regionData, Collection $items): array
    {
        $data  = [
            'name' => $regionData[0],
            'reference_time' => $regionData[1],
            'type' => $regionData[2],
            'country_id' => $regionData[3] ?? null
        ];

        if($data['type'] == ExpressLineRegionTemplate::TYPE_AREA){
            $countryNames = $items->map(fn($item) => $item[0])->values()->unique()->toArray();
            $countries = Country::query()->select(['id','name'])->whereIn('name->zh_CN', $countryNames)->get()->keyBy('name')->toArray();

            $areaNames = $items->map(fn($item) => $item[1])->values()->unique()->toArray();
            $areas = CountryArea::query()->select(['id','name'])->whereIn('name->zh_CN', $areaNames)->get()->keyBy('name')->toArray();

            $subAreaNames = $items->map(fn($item) => $item[2])->values()->unique()->toArray();
            $subAreas = CountryArea::query()->with(['parent:id,name'])->select(['id','parent_id','name'])->whereIn('name->zh_CN', $subAreaNames)->get();

            $data['areas'] = $items->map(function ($item) use ($countries, $areas, $subAreas) {

                if(empty($item[2])) return [];

                $subArea = $subAreas->first(function ($subArea) use ($item){
                    return ($item[1] == $subArea->parent->name) && ($item[2] == $subArea->name);
                });

                return [
                    'country_id' => $countries[$item[0]]['id'] ?? '',
                    'area_id' => $areas[$item[1]]['id'] ?? '',
                    'sub_area_id' => $subArea->id ?? ''
                ];

            })->values()->all();
        }else{

            $data['postcodes'] = $items->map(function ($item) {

                return [
                    'type' => 1,
                    'start' => $item[0],
                    'end' => $item[1]
                ];

            })->values()->all();
        }

        return $data;
    }


    protected function rules()
    {
        return [
            'name' => 'required|string|max:32',
            'reference_time' => 'required|string|max:32',
            'type' => 'required|in:1,2',
            'country_id' => 'required_if:type,2|integer',
            'postcodes' => 'required_if:type,2|array',
            'postcodes.*.type' => 'required|in:1,2,3',
            'postcodes.*.start' => 'required|regex:/\d+/',
            'postcodes.*.end' => 'sometimes|nullable|regex:/\d+/',
            'areas' => 'required_if:type,1|array',
            'areas.*.country_id' => 'required',
            'areas.*.area_id' => 'sometimes|nullable',
            'areas.*.sub_area_id' => 'sometimes|nullable',
        ];
    }
}
