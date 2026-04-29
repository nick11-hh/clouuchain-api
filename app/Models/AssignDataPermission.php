<?php

namespace App\Models;

use App\Services\Admin\JavaAdminAuthService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class AssignDataPermission extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_assign_data_permissions';

    protected $guarded = [];

    const CUSTOMER_PERMISSION = 'customer';


    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }

    public static function getCustomers($adminId = 0)
    {
        $adminId = $adminId ?: getAdminId();
        $cacheKey = 'admin_assign_customers_' . $adminId;
        return self::getPermissions($cacheKey, self::CUSTOMER_PERMISSION, $adminId);
    }

    public static function getPermissions($cacheKey, $permissionType, $adminId = 0)
    {
        if (empty($adminId)) return [];
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($adminId, $permissionType) {
            $adminIds = [$adminId];

            // 查询分配的数据范围
            $rangeType = DataRangeTypePermission::RANGE_TYPE_MYSELF;
            $dataRangeType = DataRangeGroup::query()->with(['rangeTypePermissions' => function ($query) use ($adminId, $permissionType) {
                return $query->where('data_type', $permissionType);
            }])->whereHas('dataRangeGroupAdmins', function ($query) use ($adminId) {
                return $query->where('admin_id', $adminId);
            })->first();
            if (!empty($dataRangeType)) {
                $rangeTypePermission = $dataRangeType->rangeTypePermissions[0] ?? [];
                if (!empty($rangeTypePermission)) {
                    $rangeType = $rangeTypePermission->range_type;
                    if ($rangeType === DataRangeTypePermission::RANGE_TYPE_PART) {
                        $adminIds = array_merge($adminIds, $rangeTypePermission->range_value);
                    }
                }
            }

            // 如果数据权限范围是查询所有数据则直接返回
            if ($rangeType === DataRangeTypePermission::RANGE_TYPE_ALL) {
                return ['range_type' => $rangeType, 'permissions' => []];
            }

            $permissions = self::query()->whereIn('admin_id', $adminIds)->where('permission_type', $permissionType)
                ->get()->pluck('permission_id')->unique()->toArray();
            return ['range_type' => $rangeType, 'permissions' => $permissions];
        });
    }
}
