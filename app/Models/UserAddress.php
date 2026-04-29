<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\LikeScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserAddress extends Model
{
    public const IS_DEFAULT = 1;

    public const STATUS_AUDITED = 1;
    public const STATUS_PENDING = 0;
    public const STATUS_REFUSED = 2;

    use Basis, LikeScope;

    protected $table = 'dsp_user_address';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'custom_tags' => 'array'
    ];

    protected $appends = [
        'country_name',
    ];

    /**
     * 地址中的国家
     * @return HasOne
     */
    public function country(): HasOne
    {
        return $this->hasOne(Country::class, 'id', 'country_id')
            ->withoutGlobalScope('enabled');
    }

    /**
     * 地址中的区域
     * @return HasOne
     */
    public function area(): HasOne
    {
        return $this->hasOne(CountryArea::class, 'id', 'area_id')
            ->withoutGlobalScope('enabled');
    }

    /**
     * 地址中的子区域
     * @return HasOne
     */
    public function subArea(): HasOne
    {
        return $this->hasOne(CountryArea::class, 'id', 'sub_area_id')
            ->withoutGlobalScope('enabled');
    }

    /**
     * 地址中的子区域
     * @return HasOne
     */
    public function lowArea(): HasOne
    {
        return $this->hasOne(CountryArea::class, 'id', 'low_area_id')
            ->withoutGlobalScope('enabled');
    }

    /**
     * 所属用户
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 所属自提点
     * @return BelongsTo
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(SelfPickupStation::class, 'station_id', 'id');
    }

    /**
     * 审核记录
     *
     * @return HasMany
     */
    public function auditRecords(): HasMany
    {
        return $this->hasMany(UserAddressAuditRecord::class, 'address_id', 'id');
    }

    /**
     * 地址标签
     *
     * @return BelongsToMany
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            UserAddressTag::class,
            'dsp_user_addresses_tags',
            'address_id',
            'tag_id'
        );
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseAddress::class, 'warehouse_id', 'id');
    }

    public function getCountryNameAttribute(): string
    {
        return $this->country ? $this->country->name : '';
    }
}
