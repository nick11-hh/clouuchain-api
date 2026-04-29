<?php

namespace App\Services\Admin;

use App\Lib\Code;
use App\Models\CustomGroup;
use App\Exceptions\AccidentException;

class CustomGroupService extends BaseService
{
    public function __construct()
    {
        $this->model = new CustomGroup();
        $this->formData = request()->all();
    }

    public function index()
    {
        $groupName = $this->formData['group_name'] ?? '';
        $pageSize = $this->formData['size'] ?? 10;
        $query = $this->model::query()->withCount('customs');
        $query->when($groupName, function ($query) use ($groupName) {
           return $query->where('group_name', 'like', "%{$groupName}%");
        });
        return $query->latest()->paginate($pageSize);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        return $this->model::query()->findOrFail($id);
    }

    /**
     * @param $params
     * @return mixed
     */
    public function store($params)
    {
        validator($params, $this->rules())->validate();
        $exist = CustomGroup::query()->where('group_name', $params['group_name'])->first();
        if ($exist) throw new AccidentException('已存在相同的分组名称', Code::OPERATE_FAIL);
        $customGroupData = CustomGroup::init($params);
        return $this->model::create($customGroupData);
    }

    /**
     * @param $id
     * @param $params
     * @return mixed
     */
    public function update($id, $params)
    {
        $customGroup = $this->model::query()->findOrFail($id);
        $exist = CustomGroup::query()->where('group_name', $params['group_name'])->where('id', '!=', $id)->first();
        if ($exist) throw new AccidentException('已存在相同的分组名称', Code::OPERATE_FAIL);
        if (isset($params['group_name'])) $customGroup->group_name = $params['group_name'];
        if (isset($params['description'])) $customGroup->description = $params['description'];
        if (isset($params['menu_limit'])) $customGroup->group_name = $params['menu_limit'];
        return $customGroup->save();
    }


    /** 删除用户组
     * @param $params
     * @return mixed
     * @throws \Exception
     */
    public function deletes($params)
    {
        $ids = $params['ids'];
        if (empty($ids)) throw new AccidentException('请选择需要删除的用户', Code::OPERATE_FAIL);
        return $this->model::whereIn('id', $params['ids'])
            ->where('is_default', 0)->delete();
    }

    protected function rules()
    {
        return [
            'group_name' => 'required|string|between:1,30',
            'description' => 'sometimes|nullable|string'
        ];
    }
}
