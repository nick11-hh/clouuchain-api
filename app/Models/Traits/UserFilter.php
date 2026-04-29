<?php
/**
 * @Author: h9471
 * @Created: 2020/2/26 14:43
 */

namespace App\Models\Traits;

use App\Models\Admin;
use App\Models\DataPermission;
use App\Models\DPAdminUser;
use App\Models\Order;
use App\Models\Package;
use App\Models\TransactionRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait UserFilter
{
    protected static $userNeedFilter = [
        'admin/packages',
        'admin/orders',
        'admin/users',
        'admin/transaction-records',
    ];


    public static function bootUserFilter()
    {
        $httpPath = self::userRouterChecker(\request(), self::$userNeedFilter);
        if (!$httpPath) {
            return;
        };

        $admin = auth('admin')->user();

        if (!($admin instanceof Admin)) {
            return;
        }

        if (!$admin->data_permission) {
            return;
        }

        $dp = DataPermission::query()
            ->where('company_id', $admin->company_id)
            ->where('http_path', $httpPath)
            ->first();

        /**@var Collection $dpUsers */
        $dpUsers = DPAdminUser::query()
            ->where('admin_id', $admin->id)
            ->where('dp_id', $dp->id)
            ->get();

        if ($dpUsers->isEmpty()) return;

        static::addGlobalScope('userFilter', function (Builder $builder) use ($dpUsers, $httpPath) {

            $model = $builder->getModel();

            $dpUsers->groupBy('field_id')->each(function ($cUsers, $field) use ($builder, $model, $httpPath) {

                $values = $cUsers->pluck('id_value')->all();

                if (($model instanceof User) && ($httpPath == 'admin/users')) {

                    $column = match ($field) {
                        DataPermission::FIELD_GROUP => 'user_group_id',
                        DataPermission::FIELD_INVITOR => 'invite_id',
                        DataPermission::FIELD_CUSTOMER => 'customer_id',
                        DataPermission::FIELD_SALE => 'sale_id',
                    };

                    $builder->whereIn($column, $values);
                    return;
                }

                $relation = null;
                if ($model instanceof Package) {
                    $relation = 'owner.' . $field;
                }

                if ($model instanceof Order) {
                    $relation = 'user.' . $field;
                }

                if ($model instanceof TransactionRecord) {
                    $relation = 'user.' . $field;
                }

                if (!$relation) return;

                $builder->whereHas($relation, function ($qGroup) use ($values) {
                    $qGroup->whereIn('id', $values);
                });

            });
        });
    }


    /**
     * 路由检查器
     * @param Request $request
     * @param array $rules
     * @return bool
     */
    protected static function userRouterChecker(Request $request, array $rules)
    {
        return collect($rules)
            ->first(function ($except) use ($request) {
                if ($except !== '/') {
                    $except = trim($except, '/');
                }

                return $request->is('api/' . $except)
                    || Str::startsWith($request->getPathInfo(), '/api/' . $except);
            });
    }
}
