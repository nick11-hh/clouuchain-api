<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 仓库货位区域
 * Class WarehouseGoodsAllocation
 * @package App\Models
 * @property int for_big
 */
class WarehouseGoodsAllocationArea extends Model
{
    use Basis,
        HasValidateUnique;

    public const REUSE_NONE = 0;
    public const REUSE_SAME_USER = 1;
    public const REUSE_SAME_USER_LIMITED = 2;
    public const REUSE_USER_LOCK = 3;

    // 货区用途
    const USE_JIYUN = 1;
    const USE_SHOP = 2;
    const USE_TYPE_LIST = [
        self::USE_JIYUN => '集运货区',
        self::USE_SHOP => '商城货区',
    ];

    protected $table = 'dsp_warehouse_goods_allocation_areas';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    /**
     * 拥有的货位
     *
     * @return HasMany
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(WarehouseGoodsAllocation::class, 'area_id', 'id');
    }

    /**
     * 所属仓库
     * @return BelongsTo
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseAddress::class, 'warehouse_id', 'id');
    }

    public function scopeJiyun(Builder $query)
    {
        $query->where('use_type', self::USE_JIYUN);
    }

    public function scopeShop(Builder $query)
    {
        $query->where('use_type', self::USE_SHOP);
    }
}
