<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminGroupModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_admin_groups';

    public function admin(): HasMany
    {
        return $this->hasMany(Admin::class, 'group_id', 'id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'dsp_admin_group_permissions',
            'admin_group_id',
            'permission_id'
        );
    }

    public function routeMenus(): BelongsToMany
    {
        return $this->belongsToMany(
            RouteMenuModel::class,
            'dsp_admin_groups_route_menus',
            'admin_group_id',
            'route_menu_id'
        );
    }

    public function isSuperGroup()
    {
        return $this->getKey() === static::query()->first()->getKey();
    }

    /**
     * @return BelongsToMany
     */
    public function warehouses()
    {
        return $this->belongsToMany(
            WarehouseAddress::class,
            'dsp_admin_group_warehouses',
            'admin_group_id',
            'warehouse_id'
        );
    }

}
