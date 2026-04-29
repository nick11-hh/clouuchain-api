<?php

namespace App\Models;

use App\Models\Traits\Basis;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * 仓库货位
 * Class WarehouseGoodsAllocation
 * @package App\Models
 */
class WarehouseGoodsAllocation extends Model
{
    use Basis;

    protected $table = 'dsp_warehouse_goods_allocations';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];


    public const UNLOCKED = 0;
    public const LOCKED = 1;

    /**
     * 所属区域
     * @return BelongsTo
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(WarehouseGoodsAllocationArea::class, 'area_id', 'id');
    }

    /**
     * 所属仓库
     * @return BelongsTo
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseAddress::class, 'warehouse_id', 'id');
    }

    /**
     * 未使用
     *
     * @param  Builder  $builder
     */
    public function scopeIsUsed(Builder $builder)
    {
        $builder->whereColumn('used_count', '>=', 'max_count');
    }

    /**
     * 已使用
     *
     * @param  Builder  $builder
     */
    public function scopeNotUsed(Builder $builder)
    {
        $builder->whereColumn('used_count', '<', 'max_count');
    }

    public function scopeWarehouse(Builder $builder, int $warehouseId)
    {
        $builder->where('warehouse_id', $warehouseId);
    }

    public function scopeIsLocked(Builder $builder)
    {
        $builder->where('is_locked', 1);
    }

    public function scopeNotLocked(Builder $builder)
    {
        $builder->where('is_locked', 0);
    }

    public function scopeNoPackageSpecialYes(Builder $builder)
    {
        $builder->whereHas('area',function ($query){
            $query->where('no_package_special', 1);
        });
    }

    public function scopeNoPackageSpecialNo(Builder $builder)
    {
        $builder->whereHas('area',function ($query){
            $query->where('no_package_special', 0);
        });
    }

    /**
     * @return bool
     */
    public function setUsed()
    {
        return $this->update([
            'used_count' => DB::raw('used_count + 1'),
        ]);
    }

    /**
     * @return bool
     */
    public function setNotUsed()
    {
        return $this->update([
            'used_count' => DB::raw('IF(used_count < 1, 0, used_count -1)'),
        ]);
    }

    /**
     * 滿了
     *
     * @return bool
     */
    public function isFull()
    {
        return $this->used_count >= $this->max_count;
    }

    public function enableCount()
    {
        return $this->max_count - $this->used_count;
    }
}
