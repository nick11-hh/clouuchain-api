<?php

namespace App\Services\Admin;


use App\Exceptions\AccidentException;
use App\Lib\Code;
use App\Lib\Language;
use App\Models\Admin;
use App\Models\AdminDepartment;
use App\Models\DataRangeGroup;
use App\Models\DataRangeGroupAdmin;
use App\Models\DataRangeTypePermission;
use App\Models\Department;
use App\Models\Goods;
use App\Services\Base\PermissionBaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DataRangeGroupService extends BaseService
{
    public $filterRules = [
        'name'                          => ['=', 'name'],
        'creator_id'                    => ['=', 'creator_id'],
        'dataRangeGroupAdmins:admin_id' => ['=', 'assign_staff_id'],
    ];


    public function __construct()
    {
        $this->model = new DataRangeGroup();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with(['creator'])->withCount(['admins']);
        $this->query->latest();
        return parent::index();
    }

    public function show($id)
    {
        return $this->model::query()->with(['creator', 'rangeTypePermissions'])->findOrFail($id);
    }

    /**
     * @throws \Throwable
     */
    public function store($params)
    {
        validator($params, $this->rules())->validate();
        $exist = $this->model::query()
            ->where('name', $params['name'])
            ->first();
        throw_if(!empty($exist), new AccidentException('已存在相同名称的数据范围组', Code::OPERATE_FAIL));
        return DB::transaction(function () use ($params) {
            $dataRangeGroupData = $this->model::init($params, 1);
            $dataRangeGroup = $this->model::query()->create($dataRangeGroupData);
            foreach ($params['range_type_permission'] as $permission) {
                DataRangeTypePermission::query()->updateOrCreate([
                    'data_range_group_id' => $dataRangeGroup->id,
                    'data_type' => $permission['data_type'],
                ], [
                    'range_type' => $permission['range_type'],
                    'range_value' => $permission['range_value'] ?? []
                ]);
            }
            return $dataRangeGroup;
        });
    }

    public function update($id, $params)
    {
        validator($params, $this->rules())->validate();
        $dataRangeGroup = $this->model::query()->findOrFail($id);
        $exist = $this->model::query()
            ->where('name', $params['name'])
            ->where('id', '!=', $id)
            ->first();
        throw_if(!empty($exist), new AccidentException('已存在相同名称的数据范围组', Code::OPERATE_FAIL));
        return DB::transaction(function () use ($id, $params, $dataRangeGroup) {
            $dataRangeGroupData = $this->model::init($params);
            $dataRangeGroup->update($dataRangeGroupData);
            foreach ($params['range_type_permission'] as $permission) {
                DataRangeTypePermission::query()->updateOrCreate([
                    'data_range_group_id' => $dataRangeGroup->id,
                    'data_type' => $permission['data_type'],
                ], [
                    'range_type' => $permission['range_type'],
                    'range_value' => $permission['range_value'] ?? []
                ]);
            }
            $dataRangeGroupAdmin = DataRangeGroupAdmin::query()->where('data_range_group_id', $id)->get();
            // 清除数据权限缓存
            foreach ($dataRangeGroupAdmin as $admin) {
                PermissionBaseService::clearPermissionCache($admin->admin_id);
            }
            return $dataRangeGroup;
        });

    }

    /**
     * @throws \Throwable
     * @throws ValidationException
     */
    public function deletes($params)
    {
        validator($params, [
            'ids' => 'required|array',
        ])->validate();

        $ids = $params['ids'];
        $dataRangeGroupAdmin = DataRangeGroupAdmin::query()->whereIn('data_range_group_id', $ids)->get();
        // 清除数据权限缓存
        foreach ($dataRangeGroupAdmin as $admin) {
            PermissionBaseService::clearPermissionCache($admin->admin_id);
        }
        return DB::transaction(function () use ($ids) {
            DataRangeGroupAdmin::query()->whereIn('data_range_group_id', $ids)->delete();
            return $this->model::query()->whereIn('id', $ids)->delete();
        });

    }

    public function getStaffList($id, $params)
    {
        $this->model::query()->findOrFail($id);
        return Admin::query()->whereHas('dataRangeGroups', function ($query) use ($id) {
            $query->where('data_range_group_id', $id);
        })->when($params['name'] ?? '', function ($query) use ($params) {
            $query->where(function ($query) use ($params) {
                $query->where('username', 'like', '%' . $params['name'] . '%');
            });
        })->latest()->paginate($params['size'] ?? 5);
    }

    public function getEnableAssignStaffList()
    {
        return Admin::query()->whereDoesntHave('dataRangeGroups')->latest()->get();
    }

    public function assignStaff($id, $params)
    {
        validator($params, [
            'admin_ids' => 'required|array',
            'admin_ids.*' => 'required|int',
        ])->validate();
        $dataRangeGroup = $this->model::query()->findOrFail($id);
        return DB::transaction(function () use ($dataRangeGroup, $params) {
            foreach ($params['admin_ids'] as $adminId) {
                DataRangeGroupAdmin::query()->updateOrCreate([
                    'admin_id' => $adminId,
                ], [
                    'data_range_group_id' => $dataRangeGroup->id,
                ]);

                // 清除数据权限缓存
                PermissionBaseService::clearPermissionCache($adminId);
            }
            return true;
        });
    }

    public function removeStaff($id, $params)
    {
        validator($params, [
            'admin_ids' => 'required|array',
            'admin_ids.*' => 'required|int',
        ])->validate();
        return DB::transaction(function () use ($params) {
            foreach ($params['admin_ids'] as $adminId) {
                DataRangeGroupAdmin::query()->where([
//                    'data_range_group_id' => $dataRangeGroup->id,
                    'admin_id' => $adminId,
                ])->delete();
                // 清除数据权限缓存
                PermissionBaseService::clearPermissionCache($adminId);
            }
            return true;
        });
    }




    public function rules()
    {
        return [
            'name' => 'required|string|max:50',
            'description' => 'sometimes|nullable|string|max:255',
            'range_type_permission' => 'required|array',
            'range_type_permission.*.data_type' => 'required|string',
            'range_type_permission.*.range_type' => 'required|string',
            'range_type_permission.*.range_value' => 'required_if:range_type,part|array',
        ];
    }

}
