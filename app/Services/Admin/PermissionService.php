<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\Custom;
use App\Services\Base\PermissionBaseService;
use App\Services\Collect\Platform\Y1688\Y1688Service;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class PermissionService extends BaseService
{
    public function __construct()
    {
        $this->request = request();
        $this->formData = $this->request->all();
        $this->setFilterRules();
    }

    public function staff()
    {
        return Admin::query()->with('assignDataPermissions')->whereHas('assignDataPermissions', function ($query) {
           $query->where('permission_id', $this->formData['permission_id'])->where('permission_type', $this->formData['permission_type']);
        })->paginate();
    }

    public function customer()
    {
        return Custom::query()->with('assignDataPermissions')->whereHas('assignDataPermissions', function ($query) {
            $query->where('admin_id', $this->formData['admin_id']);
        })->paginate($this->formData['size'] ?? 10);
    }

    public function assignDataPermissions($params)
    {
        validator($params, [
            'admin_id' => 'required|integer',
            'permission_id' => 'required|integer',
            'permission_type' => 'required|string',
        ])->validate();

        $admin = Admin::query()->findOrFail($params['admin_id']);
        PermissionBaseService::assignDataPermission($admin->id, $params['permission_id'], $params['permission_type']);
        return true;
    }

    public function removeDataPermissions($params)
    {
        validator($params, [
            'admin_id' => 'required|integer',
            'permission_id' => 'required|integer',
            'permission_type' => 'required|string',
        ])->validate();

        $admin = Admin::query()->findOrFail($params['admin_id']);
        PermissionBaseService::removeDataPermission($admin->id, $params['permission_id'], $params['permission_type']);
        return true;
    }

}
