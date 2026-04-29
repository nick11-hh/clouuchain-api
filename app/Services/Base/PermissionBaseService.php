<?php

namespace App\Services\Base;

use App\Models\Admin;
use App\Models\AdminDepartment;
use App\Models\AssignDataPermission;
use Illuminate\Support\Facades\Cache;

class PermissionBaseService
{
    /** 分配员工数据权限
     * @param $adminId
     * @param $permissionId
     * @param $permissionType
     * @return void
     */
    public static function assignDataPermission($adminId, $permissionId, $permissionType)
    {
        AssignDataPermission::query()->firstOrCreate([
            'admin_id' => $adminId,
            'permission_type' => $permissionType,
            'permission_id' => $permissionId
        ]);
        self::clearPermissionCache($adminId);
    }

    /** 分配员工数据权限
     * @param $adminId
     * @param $permissionId
     * @param $permissionType
     * @return void
     */
    public static function removeDataPermission($adminId, $permissionId, $permissionType)
    {
        AssignDataPermission::query()->where([
            'admin_id' => $adminId,
            'permission_type' => $permissionType,
            'permission_id' => $permissionId
        ])->delete();
        self::clearPermissionCache($adminId);
    }

    /** 清除权限缓存
     * @param $adminId
     * @return void
     */
    public static function clearPermissionCache($adminId)
    {
        // 刷新缓存
        $cacheKey = 'admin_assign_customers_' . $adminId;
        Cache::forget($cacheKey);
        $admin = Admin::query()->find($adminId);
        // 部门主管的缓存也清除
        if ($admin->department_id ?? 0) {
            $departmentMains = AdminDepartment::query()->where('department_id', $admin->department_id)->get();
            foreach ($departmentMains as $main) {
                $cacheKey = 'admin_assign_customers_' . $main->admin_id;
                Cache::forget($cacheKey);
            }
        }
    }

}
