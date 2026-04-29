<?php

namespace App\Models\Traits;

use App\Models\Admin;
use App\Models\AssignDataPermission;
use App\Models\DataRangeTypePermission;

/**
 *  客户数据权限隔离
 */
trait CustomerFilter
{
    public static function bootCustomerFilter()
    {
        static::addGlobalScope('customer_filter', function ($builder) {

            if (auth()->user() instanceof Admin) {

                // 超级管理员
                if (auth()->user()->group_id === 1) return;

                $column = 'customer_id';
                // custom 模型过滤id字段
                if (get_class() === 'App\Models\Custom') $column = 'id';

                $permissionData = AssignDataPermission::getCustomers();

                if ($permissionData['range_type'] == DataRangeTypePermission::RANGE_TYPE_ALL) return;

                $builder->whereIn($column, $permissionData['permissions']);
            }

        });
    }
}
