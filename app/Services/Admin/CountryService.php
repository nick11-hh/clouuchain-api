<?php

/**
 * @Author: h9471
 * @Created: 2020/06/22 11:40
 */

namespace App\Services\Admin;

use App\Http\Resources\CountryAreaList;
use App\Http\Resources\CountryWithoutAreaList;
use App\Lib\Code;
use App\Models\AdminLanguages;
use App\Models\AreaNotification;
use App\Models\Country;
use App\Models\CountryArea;
use App\Models\ExpressLineModel;
use App\Models\Package;
use App\Models\SelfPickupStation;
use App\Models\Shipment;
use App\Models\Timezone;
use App\Models\UserAddress;
use App\Models\WarehouseAddress;
use App\Services\C2TTranslate;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Vtiful\Kernel\Excel;
use App\Exceptions\AccidentException;

class CountryService extends BaseService
{
    protected $orderBy = ['index' => 'asc'];

    //表头
    public $excelHeaders;
    //国家编码
    public $countryCodes ;
    //区域-国家编码
    public $areaCountryCodes ;
    //子区域-国家编码
    public $subAreaCountryCodes;
    /**
     * @var string[]
     */
    protected array $lowAreaCountryCodes;

    public function __construct(Country $country)
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->model = $country;
        $this->query = $country->newQuery();
        $this->setFilterRules();
    }

    /**
     * @param int|null $countryId
     * @param int|null $areaId
     * @return AnonymousResourceCollection
     */
    public function countryList(int $countryId = null, int $areaId = null): AnonymousResourceCollection
    {
        if ($areaId) {
            $data = CountryArea::query()
                ->where('parent_id', $areaId)
                ->with(['areas', 'areas.areas'])
                ->get();

            return CountryAreaList::collection($data);
        }

        if ($countryId) {
            $query = CountryArea::query();
            // 名称 代码搜索
            if ($kws = $this->request->input('keyword')) {
                $kws = explode(',', $kws);

                $query->where(function ($q) use ($kws) {
                    foreach ($kws as $kw) {
                        $q->orWhere('name', 'like', "%$kw%");
                    }
                });
            }

            $data = $query
                ->where('country_id', $countryId)
                ->whereNull('parent_id')
                ->with(['areas', 'areas.areas'])
                ->get();

            return CountryAreaList::collection($data);
        }
        // 名称 代码搜索
        if ($kws = $this->request->input('keyword')) {
            $kws = explode(',', $kws);

            $data = Country::query()
                ->where(function ($q) use ($kws) {
                    foreach ($kws as $kw) {
                        $q->orWhere('name', 'like', "%$kw%")
                            ->orWhere('code', '=', "$kw");
                    }
                })
                ->withCount('areas')
                ->orderBy('index')
                ->where('enabled', 1)
                ->get();
        } else {
            $data = Country::query()
                ->withCount('areas')
                ->orderBy('index')
                ->where('enabled', 1)
                ->get();
        }

        return CountryWithoutAreaList::collection($data);
    }

    /**
     * @param int $id
     * @param $data
     */
    public function updateCountryTrans(int $id, $data)
    {
        validator($data, ['name_trans' => 'required|array']);

        /** @var Country $country */
        $country = Country::query()->findOrFail($id);

        if ($data['name_trans'] ?? []) {
            $translations = collect($data['name_trans'])
                ->filter(fn($value) => $value)
                ->all();

            if ($translations) {
                $country->setTranslations('name', $translations);
            }

            info('setTranslations', [$country->toSql()]);
            return $country->save();
        }

        return false;
    }

    /**
     * @param  int  $countryId
     * @return Builder[]|Collection
     */
    public function areas(int $countryId): Collection|array
    {
        return CountryArea::query()
            ->where('country_id', $countryId)
            ->whereNull('parent_id')
            ->with(['areas', 'areas.areas'])
            ->get();
    }

    /**
     * @param  int  $id
     * @return Model
     */
    public function areaInfo(int $id): Model
    {
        return CountryArea::query()->findOrFail($id);
    }

    /**
     * @param  int  $areaId
     * @return Builder[]|Collection
     */
    public function subAreas(int $areaId): Collection|array
    {
        return CountryArea::query()
            ->where('parent_id', $areaId)
            ->get();
    }

    /**
     * @param  array  $data
     * @throws ValidationException
     */
    public function createArea(array $data)
    {
        validator($data, $this->areaRules())->validate();

        if ($data['parent_id'] ?? null) {
            $parent = CountryArea::query()->findOrFail($data['parent_id']);
        } else {
            $parent = Country::query()->findOrFail($data['country_id']);
        }

        return DB::transaction(function () use ($parent, $data) {
            if ($parent instanceof Country) {
                /** @var CountryArea $area */
                $area = CountryArea::query()->create([
                    'name' => $data['name'],
                    'postcode' => $data['postcode'],
                    'code' => $data['code'] ?? '',
                    'parent_id' => null,
                    'country_id' => $parent->getKey(),
                    'api_code' => $data['some_with_name'] ? $data['name'] : ($data['api_code'] ?? ''),
                ]);
            } else {
                /** @var CountryArea $area */
                $area = CountryArea::query()->create([
                    'name' => $data['name'],
                    'postcode' => $data['postcode'],
                    'code' => $data['code'] ?? '',
                    'parent_id' => $parent->getKey(),
                    'country_id' => $parent->country_id,
                    'api_code' => $data['some_with_name'] ? $data['name'] : ($data['api_code'] ?? ''),
                ]);
            }

            $translations = collect($data['name_translations'] ?? [])
                ->filter(fn ($value) => $value)
                ->all();

            if ($translations) {
                $area->setTranslations('name', $translations);
            }

            return $area->save();
        });
    }

    /**
     * @param  int  $id
     * @param  array  $data
     * @throws ValidationException
     */
    public function updateArea(int $id, array $data)
    {
        validator($data, $this->areaRules())->validate();
        /** @var CountryArea $area */
        $area = CountryArea::query()->findOrFail($id);

        if ($data['parent_id'] ?? null) {
            $parent = CountryArea::query()->findOrFail($data['parent_id']);
        } else {
            $parent = Country::query()->findOrFail($data['country_id']);
        }

        return DB::transaction(function () use ($area, $parent, $data) {
            if ($parent instanceof Country) {
                $area->update([
                    'name' => $data['name'],
                    'postcode' => $data['postcode'],
                    'code' => $data['code'] ?? '',
                    'parent_id' => null,
                    'country_id' => $parent->getKey(),
                    'api_code' => $data['some_with_name'] ? $data['name'] : ($data['api_code'] ?? ''),
                ]);
            } else {
                $area->update([
                    'name' => $data['name'],
                    'postcode' => $data['postcode'],
                    'code' => $data['code'] ?? '',
                    'parent_id' => $parent->getKey(),
                    'country_id' => $parent->country_id,
                    'api_code' => $data['some_with_name'] ? $data['name'] : ($data['api_code'] ?? ''),
                ]);
            }

            $translations = collect($data['name_translations'] ?? [])
                ->filter(fn ($value) => $value)
                ->all();

            if ($translations) {
                $area->setTranslations('name', $translations);
            }

            return $area->save();
        });
    }

    /**
     * @param  array  $ids
     * @return mixed
     */
    public function deleteAreas(array $ids): mixed
    {
        return DB::transaction(function () use ($ids) {
            if (CountryArea::query()->whereIn('id', $ids)->first()) {
                UserAddress::query()
                    ->whereIn('area_id', $ids)
                    ->orWhereIn('sub_area_id', $ids)
                    ->update([
                        'is_invalid' => 1
                    ]);
            }
            return CountryArea::query()
                ->where(function ($query) use ($ids) {
                    $query->whereIn('id', $ids)
                        ->orWhereIn('parent_id', $ids);
                })->delete();
        });
    }

    /**
     * @param array $data
     * @return mixed
     * @throws ValidationException
     */
    public function updateIndex(array $data): mixed
    {
        unset($data['uuid']);
        $data = validator($data, $this->rules())->validate();

        return DB::transaction(function () use ($data) {
            foreach ($data as $datum) {
                Country::query()
                    ->findOrFail($datum['id'])
                    ->update(['index' => $datum['index']]);
            }

            return true;
        });
    }

    /**
     * @param int $id
     * @return bool
     * @throws Exception
     */
    public function destroy(int $id): bool
    {
        DB::transaction(function () use ($id) {
            if (Package::query()->where('country_id', $id)->count()
                || Shipment::query()->where('destination_country_id', $id)->count()
                || UserAddress::query()->where('country_id', $id)->count()
                || $this->inWarehouseCountryIds($id)
                || $this->inExpressLineCountryIds($id)
            ) {
                throw new AccidentException('当前国家已关联系统内包裹、订单等数据，不支持删除', Code::OPERATE_FAIL);
            }

            $country = Country::query()->findOrFail($id);

            $areaIds = $country->areas->modelKeys();

            CountryArea::query()->whereIn('parent_id', $areaIds)->delete();

            CountryArea::query()->whereKey($areaIds)->delete();

            Timezone::query()->where('timezone',$country->timezone)->delete();

            return Country::query()->findOrFail($id)->delete();
        });

        return true;
    }

    /**
     * @param  int  $id
     * @param  int  $status
     * @return mixed
     */
    public function setStatus(int $id, int $status): mixed
    {
        $country = Country::query()->findOrFail($id);

        if ($status === 0) {
            return DB::transaction(function () use ($country) {
                //解除与仓库和线路的关联
                DB::table('dsp_express_line_country')
                    ->where('country_id', $country->getKey())
                    ->delete();
                DB::table('dsp_warehouse_country')
                    ->where('country_id', $country->getKey())
                    ->delete();
                //停用这个国家的自提点
                SelfPickupStation::query()
                    ->where('country_id', $country->getKey())
                    ->update(['enabled' => 0]);

                $country->update(['enabled' => 0]);

                return true;
            });
        } else {
            SelfPickupStation::query()
                ->where('country_id', $country->getKey())
                ->update(['enabled' => 1]);

            return $country->update(['enabled' => 1]);
        }
    }

    /**
     * @param  int  $id
     * @param  int  $status
     * @return bool
     */
    public function setAreaStatus(int $id, int $status): bool
    {
        $area = CountryArea::query()->findOrFail($id);

        return $area->update(['enabled' => $status]);
    }

    /**
     * @return LengthAwarePaginator
     */
    public function notificationList()
    {
        return AreaNotification::with(['areas', 'areas.parent', 'areas.country'])->pageSize();
    }

    /**
     * @return Builder|Builder[]|Collection|Model|null
     */
    public function notificationInfo(int $id): Model|Collection|Builder|array|null
    {
        return AreaNotification::with(['areas', 'areas.parent', 'areas.country'])->findOrFail($id);
    }

    /**
     * @param  array  $ids
     * @return array
     */
    public function getNotificationByAreaId(array $ids): array
    {
        $contents = [];

        $areas = CountryArea::query()
            ->with('notification')
            ->whereKey($ids)
            ->get();

        foreach ($areas as $area) {
            if ($area->notification) {
                $contents[] =  $area->notification->content;
            }
        }

        $areas = CountryArea::query()
            ->with('notification')
            ->whereKey($areas->pluck('parent_id')->values())
            ->get();

        foreach ($areas as $area) {
            if ($area->notification) {
                $contents[] =  $area->notification->content;
            }
        }

        return $contents;
    }

    /**
     * @param  array  $data
     * @return bool
     * @throws ValidationException
     */
    public function createNotification(array $data): bool
    {
        $data = validator($data, $this->notificationRules())->validate();

        return DB::transaction(function () use ($data) {
            /** @var AreaNotification $notify */
            $notify = AreaNotification::query()->create([
                'content' => $data['content'],
            ]);

            //通知绑定到区域
            if ($data['area_ids'] ?? []) {
                $this->bindNotifyToArea($data['area_ids'], $notify);
            }

            $this->setTranslation($notify, 'content', $data['content_translations'] ?? []);

            return $notify->save();
        });
    }

    /**
     * @param  int  $id
     * @param  array  $data
     * @return bool
     * @throws ValidationException
     */
    public function updateNotification(int $id, array $data): bool
    {
        $data = validator($data, $this->notificationRules())->validate();

        return DB::transaction(function () use ($data, $id) {
            /** @var AreaNotification $notify */
            $notify = AreaNotification::query()->findOrFail($id);

            $notify->update([
                'content' => $data['content'],
            ]);

            $this->unbindNotifyFromArea($notify);
            //通知绑定到区域
            if ($data['area_ids'] ?? []) {
                $this->bindNotifyToArea($data['area_ids'], $notify);
            }

            $this->setTranslation($notify, 'content', $data['content_translations'] ?? []);

            return $notify->save();
        });
    }

    /**
     * @param  int  $id
     * @return bool|null
     */
    public function deleteNotification(int $id): ?bool
    {
        /** @var AreaNotification $notify */
        $notify = AreaNotification::query()->findOrFail($id);

        $this->unbindNotifyFromArea($notify);

        return $notify->delete();
    }

    /**
     * @param int $id
     * @param array $color
     * @return bool
     */
    public function updateRGBColor(int $id, array $color): bool
    {
        $country = Country::query()->findOrFail($id);

        $country->update(['rgb_color' => $color]);

        return true;
    }

    /**
     * @param UploadedFile $file
     * @return bool
     * @throws Exception
     */
    public function excelImport(UploadedFile $file): bool
    {
        $data = $this->parseExcel($file);

        try {

            $result = DB::transaction(function () use ($data) {
                $dataCountries = $data->unique(fn($c) => $c[0])->filter(fn($c) => $c[0])->map(fn($c) => $c)->all();

                $noExistCountries = [];
                foreach ($dataCountries as $dataCountry) {
                    $cName = $dataCountry[0];
                    /** @var \Khsing\World\Models\Country $country */
                    $country = \Khsing\World\Models\Country::getByName($cName);
                    if(!$country){
                        $noExistCountries[] = $cName;
                        continue;
                    }
                    //国家已经被添加
                    if ($dbCountry = Country::where('cn_name', $country->local_name)->first()) {
                        $this->translation($dbCountry, $dataCountry);
                        continue;
                    }

                    $country->callingcode = $country->callingcode ?: '0';

                    $code = (new ExpressLineService())
                        ->makeCountryCodeStartWithZero($country->callingcode);

                    $newCountry = new Country([
                        'cn_name' => $country->local_name,
                        'en_name' => $country->name,
                        'timezone' => $code,
                        'code' => $country->code,
                    ]);

                    $this->translation($newCountry, $dataCountry);

                    //国际区号有可能相同
                    if (Timezone::where('timezone', $code)->count()) {
                        $newCountry->save();
                        continue;
                    }

                    $timezone = new Timezone(
                        [
                            'timezone' => (new ExpressLineService())
                                ->makeCountryCodeStartWithZero($country->callingcode),
                        ]
                    );

                    $newCountry->save() && $timezone->save();
                }
                $names = collect($dataCountries)->map(fn($country)=>$country[0])->toArray();

                $countries = Country::query()->whereIn('name->zh_CN', $names)->get();

                $tCName = '';
                $aModel = $cModel = $dModel = null;
                foreach ($data as $key => $datum) {
                    if (is_null($datum[4])) {
                        throw new AccidentException(sprintf('第%s行邮编不能为空', $key + 1), Code::OPERATE_FAIL);
                    }

                    if ($datum[0]) {
                        $tCName = $datum[0];
                    }

                    if (!$datum[1] && !$datum[2] && !$datum[3]) {
                        continue;
                    }

                    $country = $countries->first(fn($c) => $c->name == $tCName);
                    if(!$country){
                        continue;
                    }

                    if ($datum[1] && !$datum[2]) {
                        $aModel = CountryArea::query()->updateOrCreate([
                            'name->zh_CN' => $datum[1],
                            'country_id' => $country->id,
                        ], [
                            'postcode' => !empty($datum[4]) ? $datum[4] : '0000',
                            'code' => $datum[5],
                        ]);
                    }
                    // 首行的子区域
                    if ($datum[1] && $datum[2] && !$datum[3]) {
                        $aModel = CountryArea::query()->updateOrCreate([
                            'name->zh_CN' => $datum[1],
                            'country_id' => $countries->first(fn($c) => $c->name == $tCName)->id,
                        ], [
                            'postcode' => !empty($datum[4]) ? $datum[4] : '0000',
                            'code' => $datum[5],
                        ]);

                        $cModel = CountryArea::query()->updateOrCreate([
                            'name->zh_CN' => $datum[2],
                            'country_id' => $aModel->country_id,
                            'parent_id' => $aModel->getKey(),
                        ],
                            [
                                'postcode' => !empty($datum[4]) ? $datum[4] : '0000',
                                'code' => $datum[5],
                            ]);
                    }
                    // 单独行的子区域
                    if (!$datum[1] && $datum[2] && !$datum[3]) {
                        $cModel = CountryArea::query()->updateOrCreate([
                            'name->zh_CN' => $datum[2],
                            'country_id' => $aModel->country_id,
                            'parent_id' => $aModel->getKey(),
                        ],
                            [
                                'postcode' => !empty($datum[4]) ? $datum[4] : '0000',
                                'code' => $datum[5],
                            ]);
                    }
                    // 首行的三级区域
                    if ($datum[1] && $datum[2] && $datum[3]) {
                        $aModel = CountryArea::query()->updateOrCreate([
                            'name->zh_CN' => $datum[1],
                            'country_id' => $countries->first(fn($c) => $c->name == $tCName)->id,
                        ], [
                            'postcode' => !empty($datum[4]) ? $datum[4] : '0000',
                            'code' => $datum[5],
                        ]);

                        $cModel = CountryArea::query()->updateOrCreate([
                            'name->zh_CN' => $datum[2],
                            'country_id' => $aModel->country_id,
                            'parent_id' => $aModel->getKey(),
                        ],
                            [
                                'postcode' => !empty($datum[4]) ? $datum[4] : '0000',
                                'code' => $datum[5],
                            ]);

                        $dModel = CountryArea::query()->updateOrCreate([
                            'name->zh_CN' => $datum[3],
                            'country_id' => $cModel->country_id,
                            'parent_id' => $cModel->getKey(),
                        ],
                            [
                                'postcode' => !empty($datum[4]) ? $datum[4] : '0000',
                                'code' => $datum[5],
                            ]);
                    }
                    // 单独行的三级区域
                    if (!$datum[1] && !$datum[2] && $datum[3]) {
                        $dModel = CountryArea::query()->updateOrCreate([
                            'name->zh_CN' => $datum[3],
                            'country_id' => $cModel->country_id,
                            'parent_id' => $cModel->getKey(),
                        ],
                            [
                                'postcode' => !empty($datum[4]) ? $datum[4] : '0000',
                                'code' => $datum[5],
                            ]);
                    }

                    /**@var CountryArea $aModel*/
                    $aModel && $this->translation($aModel, $datum, type: 2);

                    /**@var CountryArea $cModel*/
                    $cModel && $this->translation($cModel, $datum, type: 3);

                    $dModel && $this->translation($dModel, $datum, type: 4);

                }
                return ['status' => true, 'no_exist_countries' => $noExistCountries];
            });
        } catch (Exception $e) {
            throw $e;
        } catch (\Throwable $e) {
            info(__METHOD__.'导入失败', ['err' => $e->getMessage()]);
            throw new AccidentException('导入失败，请检查模板数据是否正确', Code::OPERATE_FAIL);
        }

        return $result;
    }

    public function setExcelHeaders($excelHeader)
    {
        //表头
        $this->excelHeaders = $excelHeader;
        //国家编码
        $this->countryCodes = AdminLanguages::excelCountryCodes();
        //区域-国家编码
        $this->areaCountryCodes = AdminLanguages::excelAreaCountryCodes();
        //子区域-国家编码
        $this->subAreaCountryCodes = AdminLanguages::excelSubAreaCountryCodes();
        //三级区域-国家编码
        $this->lowAreaCountryCodes = AdminLanguages::excelLowAreaCountryCodes();
    }


    public function translation(Country|CountryArea $model, $data, $key='name', $type = 1)
    {
        $codes = match($type){
            1 => $this->countryCodes,
            2 => $this->areaCountryCodes,
            3 => $this->subAreaCountryCodes,
            4 => $this->lowAreaCountryCodes,
            default => []
        };
        if(!$codes) return true;


        $languages = ($type == 1) ? [
            'zh_CN' => $model->cn_name,
            'en_US' => $model->en_name,
            'zh_TW' => (new C2TTranslate())->c2t($model->cn_name)
        ] : ['zh_TW' => (new C2TTranslate())->c2t($model->name)];

        foreach ($this->excelHeaders as $k => $header)
        {
            if(!empty($codes[$header]) && !empty($data[$k])){
                $languages[$codes[$header]] = $data[$k];
            }
        }

        if($languages){
            $model->setTranslations($key, $languages);
            $model->save();
        }
        return true;
    }

    public function getTemplateTypeList()
    {
        return $this->formatList($this->model::templateTypeList());
    }

    /**
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadExcelTemplate($type = null)
    {
        $type = (int)$type;
        $fileName = match($type){
            Country::TEMPLATE_TYPE_ASIA => 'CountryImportTemplateAsia.xlsx',
            Country::TEMPLATE_TYPE_EUROPE => 'CountryImportTemplateEurope.xlsx',
            Country::TEMPLATE_TYPE_AFRICA => 'CountryImportTemplateAfrica.xlsx',
            Country::TEMPLATE_TYPE_NORTH_AMERICA => 'CountryImportTemplateNA.xlsx',
            Country::TEMPLATE_TYPE_OCEANIA => 'CountryImportTemplateOceania.xlsx',
            default => 'CountryImportTemplate.xlsx',
        };

        $file = Storage::get($fileName);
        return response()->streamDownload(function () use ($file) {
            echo $file;
        }, $fileName);
    }

    /**
     * @param $file
     * @return \Illuminate\Support\Collection|\Yansongda\Supports\Collection
     * @throws Exception
     */
    protected function parseExcel($file)
    {
        try {
            $config = ['path' => $file->getPath()];

            $excel = (new Excel($config))
                ->openFile($file->getFilename())
                ->openSheet();

            $items = collect([]);
            while (($row = $excel->nextRow()) !== null) {
                if(empty($row)) break;
                if (!$row[0] && !$row[1] && !$row[2] && !$row[4]) {
                    break;
                }
                $items->push(collect($row));
            }

            //设置表头
            $this->setExcelHeaders($items[0]);

            // 删除第一行说明性数据
            unset($items[0]);

            return $items;
        } catch (\Throwable $throwable) {
            throw new AccidentException('模板数据异常，请检查模板数据是否填写完整与正确', Code::OPERATE_FAIL);
        }
    }

    /**
     * @param  array  $ids
     * @param  AreaNotification  $notification
     * @return bool
     * @throws Exception
     */
    protected function bindNotifyToArea(array $ids, AreaNotification $notification)
    {
        $ids = array_unique($ids);

        CountryArea::query()->whereKey($ids)
            ->select(['notification_id'])
            ->get()
            ->each(function ($area) {
                if ($area->notification_id) {
                    throw new AccidentException("区域通知不能重复设置", Code::OPERATE_FAIL);
                }
            });

        CountryArea::query()->whereKey($ids)->update(['notification_id' => $notification->getKey()]);

        return true;
    }

    /**
     * @return bool
     */
    protected function unbindNotifyFromArea(AreaNotification $notification)
    {
       $notification->areas()->update(['notification_id' => null]);

        return true;
    }

    /**
     * @param $model
     * @param  string  $column
     * @param  array  $transData
     */
    protected function setTranslation($model, string $column, array $transData)
    {
        if ($transData) {
            $model->setTranslations($column, $transData);
        }
    }

    /**
     * @param int $countryId
     * @return bool
     */
    protected function inWarehouseCountryIds(int $countryId)
    {
        return in_array($countryId, WarehouseAddress::with('countries')->get()
            ->pluck('countries')
            ->values()->pluck('id')
            ->toArray()
        );
    }

    /**
     * @param int $countryId
     * @return bool
     */
    protected function inExpressLineCountryIds(int $countryId)
    {
        return in_array($countryId,ExpressLineModel::with('countries')->get()
            ->pluck('countries')
            ->values()->pluck('id')
            ->toArray()
        );
    }

    public function rules()
    {
        return [
            '*.id' => 'required',
            '*.index' => 'required|integer',
        ];
    }

    public function areaRules()
    {
        return [
            'parent_id' => 'sometimes|nullable',
            'country_id' => 'required',
            'name' => 'required|string|max:150',
            'postcode' => 'required',
            'code' => 'sometimes|nullable|string',
            'api_code' => 'sometimes|nullable|string',
            'some_with_name' => 'required|in:0,1',
            'name_translations' => 'array',
        ];
    }

    protected function notificationRules()
    {
        return [
            'area_ids' => 'sometimes|nullable|array',
            'content' => 'required|string|max:1024',
            'content_translations' => 'array',
        ];
    }

    /**
     * 设置国家热门状态
     * @param int $id
     * @param int $status
     * @return bool|int
     */
    public function setHot(int $id, int $status)
    {
        return Country::query()->whereKey($id)->update(['hot' => $status]);
    }
}
