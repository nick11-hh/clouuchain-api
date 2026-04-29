<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 17:09
 */

namespace App\Services\Admin;

use Exception;
use App\Lib\Code;
use App\Models\AdminGroupModel;
use App\Models\UserGroup;
use Illuminate\Validation\ValidationException;
use Throwable;
use App\Exceptions\AccidentException;

trait HasGroupOperation
{
    /**
     * 更新信息
     * @param  int  $id
     * @param  array  $data
     * @return bool
     * @throws Exception
     */
    public function updateInformation(int $id, array $data): bool
    {
        validator($data, $this->updateRules())->validate();

        if ($this->model instanceof AdminGroupModel && $id === AdminGroupModel::first()->id) {
            throw new AccidentException('超级管理员用户组不能修改', Code::OPERATE_FAIL);
        }

        $group = $this->model::findOrFail($id);

        $this->model::validateUniqueOrFail('name_cn', $data['name_cn'], $id);

        $this->model::validateUniqueOrFail('name_en', $data['name_en'], $id);

        return $group->update(
            [
                'name_en' => $data['name_en'],
                'name_cn' => $data['name_cn'],
                'description' => $data['description'] ?? '',
            ]
        ) !== false;
    }

    /**
     * 新建组
     * @param array $data
     * @return bool
     * @throws ValidationException
     */
    public function add(array $data): bool
    {
        validator($data, $this->updateRules())->validate();

        $this->model::validateUniqueOrFail('name_cn', $data['name_cn']);

        $this->model::validateUniqueOrFail('name_en', $data['name_en']);

        $group = new $this->model([
            'name_en' => $data['name_en'],
            'name_cn' => $data['name_cn'],
            'description' => $data['description'] ?? '',
        ]);

        return $group->save();
    }

    /**
     * @throws Throwable
     */
    public function setSTGAuth($id, $stgAuth): int
    {
        throw_if(
            !in_array($stgAuth,[0, 1]),
            new AccidentException('数据错误', Code::OPERATE_FAIL)
        );

        return UserGroup::query()->whereKey($id)->update([
            'stg_auth' => $stgAuth
        ]);
    }

    /**
     * @return array
     */
    private function updateRules(): array
    {
        return [
            'name_cn' => 'required|string|max:50',
            'name_en' => 'required|string|max:50',
            'description' => 'sometimes|string|nullable|max:200',
        ];
    }
}
