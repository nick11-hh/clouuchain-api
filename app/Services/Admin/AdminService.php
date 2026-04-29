<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\Admin;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Exceptions\AccidentException;

class AdminService extends BaseService
{
    public $filterRules = [
        // 'username' => ['like', 'keyword'],
        'group_id' => ['=', 'group_id']
    ];

    public function __construct(Admin $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function index()
    {
        $this->query->with('group:id,name')->with('dataRangeGroup');

        $this->query->when(isset($this->formData['keyword']), function($query) {
            $query->where('username', 'like', $this->formData['keyword'])->orWhere('name', 'like', $this->formData['keyword']);
        });
        return parent::index();
    }

    public function store()
    {
        validator($this->formData, [
            'username' => 'required',
            'name'     => 'required',
            'phone_area_code' => 'sometimes|nullable|string'
        ], [], [
            'username' => '登录账号',
            'name'     => '姓名',
        ])->validate();

        throw_if(
            $this->model::where('username', $this->formData['username'])->first(),
            new AccidentException('操作失败，账号已存在', Code::OPERATE_FAIL)
        );

        return DB::transaction(function () {
            $data = Admin::init($this->formData);
            $admin = $this->model::query()->create($data);
            $admin->invite_code = generateEmployeeInviteCode($admin->id);
            $admin->save();
            //  数据范围
            if (!empty($this->formData['data_range_group_id'])) {
                (new DataRangeGroupService())->assignStaff($this->formData['data_range_group_id'], ['admin_ids' => [$admin->id]]);
            }
            return $admin;
        });

    }

    public function update($id)
    {
        validator($this->formData, [
            'username' => 'required',
            'name'     => 'required',
            'phone_area_code' => 'sometimes|nullable|string',
            'check_auth' => 'sometimes|nullable',
        ], [], [
            'username' => '登录账号',
            'name'     => '姓名',
        ])->validate();

        throw_if(
            $this->model::where('username', $this->formData['username'])->where('id', '<>', $id)->first(),
            new AccidentException('操作失败，账号已存在', Code::OPERATE_FAIL)
        );

        return DB::transaction(function () use ($id) {
            $admin = Admin::query()->findOrFail($id);
            $data = $this->model::init($this->formData, 2);
            $admin->update($data);
            //  数据范围
            if (empty($this->formData['data_range_group_id'])) {
                (new DataRangeGroupService())->removeStaff(0, ['admin_ids' => [$id]]);
            } else {
                (new DataRangeGroupService())->assignStaff($this->formData['data_range_group_id'], ['admin_ids' => [$id]]);
            }
            return $admin;
        });
    }

    public function deletes()
    {
        validator($this->formData, ['ids' => 'required|array'])->validate();

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('super_admin', 1)->first(),
            new AccidentException('操作失败，超级管理员不允许删除', Code::OPERATE_FAIL)
        );

        return $this->model::where('id', $this->formData['ids'])->delete();
    }

    public function enable()
    {
        validator($this->formData, [
            'ids' => 'required|array',
            'status' => [
                'required',
                Rule::in([0,1])
            ]
        ], [], [
            'ids' => '员工',
            'status' => '状态'
        ])->validate();

        throw_if(
            $this->model::whereIn('id', $this->formData['ids'])->where('super_admin', 1)->first(),
            new AccidentException('操作失败，超级管理员不允许操作', Code::OPERATE_FAIL)
        );

        return $this->model::whereIn('id', $this->formData['ids'])->update(['enable' => $this->formData['status']]);
    }

    /**
     * 修改密码
     * @return void
     */
    public function modifyPass($id)
    {
        validator($this->formData, [
            'password' => 'required',
            'verify_pass' => 'required'
        ], [], [
            'password' => '密码',
            'verify_pass' => '确认密码'
        ])->validate();

        throw_if(
            $this->formData['password'] !== $this->formData['verify_pass'],
            new AccidentException('操作失败，两次输入的密码不一致', Code::OPERATE_FAIL)
        );

        return $this->model::where('id', $id)->update(['password' => password_hash($this->formData['password'], PASSWORD_DEFAULT)]);
    }
}
