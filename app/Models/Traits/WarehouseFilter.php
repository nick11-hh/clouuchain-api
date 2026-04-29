<?php
/**
 * @Author: h9471
 * @Created: 2020/2/26 14:43
 */

namespace App\Models\Traits;

use App\Models\AdminGroupModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

trait WarehouseFilter
{
    protected static $needFilter = [
        'admin/packages',
        'admin/orders',
        'admin/shipments',
        'admin/package-packs/packages',
        'admin/app/index-data',
        'admin/inventory-warehouse',
        'admin/declare',
    ];

    public static function bootWarehouseFilter()
    {
        if (self::routerChecker(\request(), self::$needFilter)) {
            $warehouseIds = self::getWarehouseIds();

            if ($warehouseIds) {
                static::addGlobalScope('warehouseFilter', function (Builder $builder) use ($warehouseIds) {
                    $table = $builder->getModel()->getTable();
                    $builder->whereIn("{$table}.warehouse_id", $warehouseIds);
                });
            }
        }
    }

    /**
     *
     * @return mixed
     */
    protected static function getWarehouseIds()
    {
        if (\auth('admin')->guest()) {
            return [];
        }

        /** @var AdminGroupModel $group */
        $group = \auth('admin')->user()->group;
        if ($group->isSuperGroup()) {
            return [];
        }

        $group->load('warehouses');

        return $group->warehouses->modelKeys();
    }

    /**
     * 路由检查器
     * @param Request $request
     * @param array $rules
     * @return bool
     */
    protected static function routerChecker(Request $request, array $rules)
    {
        return collect($rules)
            ->contains(function ($except) use ($request) {
                if ($except !== '/') {
                    $except = trim($except, '/');
                }

                return $request->is('api/' . $except)
                    || Str::startsWith($request->getPathInfo(), '/api/' . $except);
            });
    }
}
