<?php

namespace App\Services\Admin;


use App\Exceptions\AccidentException;
use App\Lib\Code;
use App\Lib\Language;
use App\Models\Admin;
use App\Models\AdminDepartment;
use App\Models\Department;
use App\Models\Goods;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepartmentService extends BaseService
{
    public $filterRules = [
        'name'     => ['=', 'name'],
        'status'   => ['=', 'status'],
        'parent_id'   => ['=', 'parent_id'],
    ];


    public function __construct()
    {
        $this->model = new Department();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->latest();
        return parent::index();
    }

    /** 获取产品分类树
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function tree()
    {
        $query = $this->model::query()->with(['children'=> function($query) {
            $query->orderBy('created_at', 'asc');
        }])->where('parent_id', 0);
        return $query->get();
    }

    public function show($id)
    {
        return $this->model::query()->findOrFail($id);
    }

    /**
     * @throws \Throwable
     */
    public function store($params)
    {
        validator($params, $this->rules())->validate();
        $exist = $this->model::query()
            ->where('name', $params['name'])
            ->where('parent_id', $params['parent_id'] ?? 0)
            ->first();
        throw_if(!empty($exist), new AccidentException('已存在相同名称的分类', Code::OPERATE_FAIL));

        $parent = $this->model::query()->find($params['parent_id'] ?? 0);
        $params['level'] = ($parent->level ?? 0) + 1;
        $department = $this->model::init($params);
        return $this->model::query()->create($department);
    }

    public function update($id, $params)
    {
        validator($params, $this->rules())->validate();
        $department = $this->model::query()->findOrFail($id);
        $exist = $this->model::query()
            ->where('name', $params['name'])
            ->where('parent_id', $params['parent_id'] ?? 0)
            ->where('id', '!=', $id)
            ->first();
        throw_if(!empty($exist), new AccidentException('已存在相同名称的分类', Code::OPERATE_FAIL));
        $parent = $this->model::query()->find($params['parent_id'] ?? 0);
        $params['level'] = ($parent->level ?? 0) + 1;
        $departmentData = $this->model::init($params);
        return $department->update($departmentData);
    }

    public function updateStatus($params)
    {
        validator($params, [
            'ids' => 'required|array',
            'status' => 'required|int'
        ])->validate();
        $data = ['status' => $params['status']];
        return $this->model->whereIn('id', $params['ids'])->update($data);
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

        throw_if(AdminDepartment::query()->whereIn('department_id', $ids)->exists(),
                 new AccidentException('部门下面存在员工，请先移除员工', Code::OPERATE_FAIL),
        );
        throw_if(Department::query()->whereIn('parent_id', $ids)->exists(),
            new AccidentException('部门下面存在子部门，请先删除子部门', Code::OPERATE_FAIL),
        );
        return $this->model::query()->whereIn('id', $ids)->delete();
    }


    public function getStaffList($id, $params)
    {
        $department = Department::query()->findOrFail($id);
        return Admin::query()->with('adminDepartment')->whereHas('adminDepartment', function ($query) use ($department) {
            $query->where('department_id', $department->id);
        })->paginate($params['size'] ?? 10);
    }

    /**
     * @param $id
     * @param $params
     * @return true
     * @throws ValidationException
     */
    public function assignStaff($id, $params)
    {
        validator($params, [
            'admin_ids' => 'required|array',
            'is_main' => 'sometimes|nullable|int',
        ])->validate();
        $department = Department::query()->findOrFail($id);
        DB::transaction(function () use ($department, $params) {
            foreach ($params['admin_ids'] as $adminId) {
                AdminDepartment::query()->updateOrCreate([
                    'admin_id' => $adminId,
                    'department_id' => $department->id
                ], [
                    'is_main' => $params['is_main'] ?? 0,
                ]);
            }
        });
        return true;
    }

    /**
     * @param $id
     * @param $params
     * @return true
     * @throws ValidationException
     */
    public function removeStaff($id, $params)
    {
        validator($params, [
            'admin_ids' => 'required|array',
        ])->validate();
        $department = Department::query()->findOrFail($id);
        DB::transaction(function () use ($department, $params) {
            foreach ($params['admin_ids'] as $adminId) {
                AdminDepartment::query()->where(['admin_id' => $adminId, 'department_id' => $department->id])->delete();
            }
        });
        return true;
    }


    public function updateStaffMain($id, $params)
    {
        validator($params, [
            'admin_ids' => 'required|array',
            'is_main' => 'required|int'
        ])->validate();
        $department = Department::query()->findOrFail($id);
        return DB::transaction(function () use ($department, $params) {
            return AdminDepartment::query()->whereIn('admin_id', $params['admin_ids'])->where('department_id', $department->id)
                ->update([
                    'is_main' => $params['is_main'],
                ]);
        });
    }


    public function rules()
    {
        return [
            'name' => 'required|string',
            'parent_id' => 'sometimes|nullable|int',
            'description' => 'sometimes|nullable|string',
        ];
    }

}
