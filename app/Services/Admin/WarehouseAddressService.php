<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:40
 */

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\Admin;
use App\Models\AdminGroupModel;
use App\Models\Country;
use App\Models\TrackGeoLocation;
use App\Models\WarehouseAddress;
use App\Models\WarehouseGoodsAllocation;
use App\Models\WarehouseGoodsAllocationArea;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;
use App\Exceptions\AccidentException;

class WarehouseAddressService extends BaseService
{
    use HasStatusSetting;

    protected $filterRules = [
        'warehouse_name,receiver_name,phone,address,postcode' => ['like', 'keyword'],
    ];

    protected $orderBy = [
        'custom_sort' =>'asc',
        'enabled' => 'desc',
    ];

    public function __construct(WarehouseAddress $warehouseAddress)
    {
        $this->request = request();
        $this->model = $warehouseAddress;
        $this->query = $this->model->newQuery();
        $this->formData = $this->request->all();
        $this->setFilterRules();
    }

    public function getAllEnable()
    {
        $this->query->where('enabled', WarehouseAddress::ENABLE);

        return parent::index();
    }

    /**
     * @return WarehouseAddress[]|Collection
     */
    public function all(): Collection|array
    {
        if (\auth('admin')->guest()) {
            return [];
        }
        /** @var Admin $user */
        $user = \auth('admin')->user();
        /** @var AdminGroupModel $group */
        $group = $user->group;
        if ($group->isSuperGroup()) {
            $ids = [];
        } else {
            $ids = $group->warehouses->modelKeys();
        }

        if ($ids) {
            return WarehouseAddress::query()->whereKey($ids)->orderBy('custom_sort')->get();
        }

        return WarehouseAddress::query()->orderBy('custom_sort')->get();
    }

    public function simple()
    {
        return $this->model::query()->where('enabled', 1)->select(['id', 'warehouse_name'])->get();
    }

    /**
     * @return WarehouseAddress[]|Collection
     */
    public function allWithExpressLine(): Collection|array
    {
        if (\auth('admin')->guest()) {
            return [];
        }
        /** @var Admin $user */
        $user = \auth('admin')->user();
        /** @var AdminGroupModel $group */
        $group = $user->group;
        if ($group->isSuperGroup()) {
            $ids = [];
        } else {
            $ids = $group->warehouses->modelKeys();
        }

        if ($ids) {
            return WarehouseAddress::query()->select(['id', 'warehouse_name'])->with('lines')->whereKey($ids)->get();
        }

        return WarehouseAddress::with('lines:id,name')->select(['id', 'warehouse_name'])->get();
    }

    /**
     * @param array $data
     */
    public function filterList(array $data)
    {
        return WarehouseAddress::query()->whereHas('countries', function ($query) use ($data) {
            $query->where(DB::raw('dsp_country.id'), $data['country_id'] ?? 0);
        })->get();
    }
    /**
     * 更新地址
     *
     * @param  int  $id
     * @param  array  $data
     * @return bool
     * @throws Throwable
     */
    public function updateAddress(int $id, array $data): bool
    {
        validator($data, $this->updateRules())->validate();

        throw_unless(Country::isValid($data['support_countries'] ?? []), new AccidentException('国家错误', Code::OPERATE_FAIL));

        return DB::transaction(function () use ($id, $data) {
            /** @var WarehouseAddress $address */
            $address = $this->model::findOrFail($id);

            $address->update(
                [
                    'warehouse_name' => $data['warehouse_name'],
                    'receiver_name' => $data['receiver_name'],
                    'timezone' => '0086',
                    'phone' => $data['phone'],
                    'postcode' => $data['postcode'],
                    'address' => $data['address'],
                    'code' => $data['code'] ?? '',
                    'short_address' => $data['short_address'] ?? '',
                    'free_store_days' => $data['free_store_days'] ?? 0,
                    'store_fee' => ($data['store_fee'] ?? 0) * 100,
                    'is_stg' => $data['is_stg'] ?? 0,
                    'province' => $data['province'] ?? '',
                    'city' => $data['city'] ?? '',
                    'auto_location' => $data['auto_location'] ?? 0,
                ]
            );

            $address->countries()->sync(array_unique($data['support_countries']));

            //忽略经纬度更新
            if (!($data['ignore_lon_lat'] ?? false)) {
                $address->update($this->getWarehouseLngAndLat($data['address']));
            }

            return true;
        });
    }

    /**
     * 新建地址
     *
     * @param  array  $data
     * @return bool
     * @throws Throwable
     */
    public function createAddress(array $data): bool
    {
        validator($data, $this->updateRules())->validate();

        throw_unless(Country::isValid($data['support_countries']), new AccidentException('国家错误！', Code::OPERATE_FAIL));

        return DB::transaction(function () use ($data) {
            /** @var WarehouseAddress $newAddress */
            $newAddress = $this->model::create(
                [
                    'warehouse_name' => $data['warehouse_name'],
                    'receiver_name' => $data['receiver_name'],
                    'timezone' => '0086',
                    'phone' => $data['phone'],
                    'postcode' => $data['postcode'],
                    'address' => $data['address'],
                    'code' => $data['code'] ?? '',
                    'short_address' => $data['short_address'] ?? '',
                    'is_stg' => $data['is_stg'] ?? 0,
                    'province' => $data['province'] ?? '',
                    'city' => $data['city'] ?? '',
                    'auto_location' => $data['auto_location'] ?? 0,
                ]
            );

            $newAddress->countries()->attach(array_unique($data['support_countries']));

            //忽略经纬度更新
//            if (!($data['ignore_lon_lat'] ?? false)) {
//                $newAddress->update($this->getWarehouseLngAndLat($data['address']));
//            }

            return true;
        });
    }

    /**
     * @param array $data
     * @return mixed
     * @throws ValidationException
     */
    public function sort(array $data): mixed
    {
        $data = validator($data, $this->rules())->validate();

        return DB::transaction(function () use ($data) {
            foreach ($data as $datum) {
                WarehouseAddress::query()
                    ->findOrFail($datum['id'])
                    ->update(['custom_sort' => $datum['index']]);
            }

            return true;
        });
    }

    /**
     * @param  int  $id
     * @param  array  $data
     * @return bool
     * @throws ValidationException
     */
    public function updateTranslateData(int $id, array $data)
    {
        validator($data, $this->translateRules())->validate();

        /** @var WarehouseAddress $warehouseAddress */
        $warehouseAddress = $this->model::query()->findOrFail($id);

        $warehouseAddress->setTranslations('warehouse_name', [$data['language'] => $data['warehouse_name']]);
        $warehouseAddress->setTranslations('address', [$data['language'] => $data['address']]);
        $warehouseAddress->setTranslations('receiver_name', [$data['language'] => $data['receiver_name']]);
//        $warehouseAddress->setTranslations('tips', [$data['language'] => $data['tips'] ?? '']);

        return $warehouseAddress->save();
    }

    /**
     * 更新仓库的经纬度
     *
     * @param  string  $address
     * @return array
     * @throws Exception
     */
    public function getWarehouseLngAndLat(string $address)
    {
        $result = TrackGeoLocation::getLocationUseContext($address, true);

        //精确度太低是不行的
        if (!$result || $result['reliability'] < 7 || $result['level'] < 9) {
            throw new AccidentException('当前地址可能不够精确，请精确到门牌号', Code::OPERATE_FAIL);
        }

        return $result['location'];
    }

    /**
     * 删除
     *
     * @param  array  $id
     * @return bool
     * @throws Throwable
     */
    public function delete(array $id): bool
    {
        $count = WarehouseAddress::with('lines')
            ->whereIn('id', $id)
            ->get()
            ->pluck('lines')->flatten()->count();

        throw_if($count > 0, new AccidentException('该仓库已经分配有线路，不能删除', Code::OPERATE_FAIL));

        DB::transaction(function () use ($id) {
            $areaIds = WarehouseGoodsAllocationArea::query()
                ->where('warehouse_id', $id)
                ->get()
                ->modelKeys();

            WarehouseGoodsAllocation::query()->whereIn('area_id', $areaIds)->delete();
            WarehouseGoodsAllocationArea::query()->whereKey($areaIds)->delete();

            return parent::delete($id);
        });

        return true;
    }

    /**
     * 设置是否显示
     *
     * @param int $id
     * @param bool $show
     * @return bool
     */
    public function setShow(int $id, bool $show): bool
    {
        $setting = $this->model::findOrFail($id);

        return $setting->update(['show' => (int) $show]);
    }

    protected function translateRules()
    {
        return array_merge(parent::translateRules(), [
            'warehouse_name' => 'required|string|max:50',
            'address' => 'required|string|max:250',
//            'tips' => 'required|string|max:250',
            'receiver_name' => 'required|string|max:50',
        ]);
    }

    private function updateRules(): array
    {
        return [
            'warehouse_name' => 'required|string|max:50',
            'receiver_name' => 'required|string|max:50',
            'support_countries' => 'required|array',
            'support_countries.*' => 'required|integer|gt:0',
            'phone' => 'required|string|between:8,18',
            'postcode' => 'required|string|max:12',
            'address' => 'required|string|max:250',
            'code' => 'sometimes|nullable',
            'ignore_lon_lat' => 'sometimes|nullable|boolean',
            'auto_location' => 'integer|in:0,1',
//            'tips' => 'required|string|max:250',
            'short_address' => 'sometimes|nullable|string|max:120',
            'free_store_days' => 'sometimes|nullable|integer|gte:0|max:365',
            'store_fee' => 'sometimes|nullable|numeric|gte:0',
            'is_stg' => 'sometimes|nullable|int|in:0,1',
            'province' => 'sometimes|nullable|string|max:50',
            'city' => 'sometimes|nullable|string|max:50',
        ];
    }

    public function rules()
    {
        return [
            '*.id' => 'required',
            '*.index' => 'required|integer',
        ];
    }
}
