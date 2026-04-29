<?php

namespace App\Models;

use App\Models\Traits\Basis;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Class SalePrice
 * @package App\Models
 * @property int $scope
 * @property bool $enabled
 * @property Carbon $effect_at
 * @property Carbon $expire_at
 * @property string $discount
 * @property int $discount_type
 */
class SalePrice extends Model
{
    use Basis;

    public const SCOPE_ALL_USERS = 0;
    public const SCOPE_USER_GROUP = 1;
    public const SCOPE_USER_LEVEL = 2;
    public const SCOPE_USERS = 3;
    public const SCOPE_MIX = 99;

    protected $table = 'dsp_sale_prices';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'effect_at' => 'datetime',
        'expire_at' => 'datetime',
    ];

    protected $appends = [];

    /**
     * @return BelongsToMany
     */
    public function expressLines(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpressLineModel::class,
            'dsp_sale_prices_express_lines',
            'sale_price_id',
            'express_line_id'
        );
    }

    /**
     * @return BelongsToMany
     */
    public function users()
    {
        return $this->morphToMany(
            User::class,
            'service',
            'dsp_sale_prices_users',
            'sale_price_id',
            'user_id'
        );
    }

    /**
     * @return BelongsToMany
     */
    public function userGroups()
    {
        return $this->morphToMany(
            UserGroup::class,
            'service',
            'dsp_sale_prices_user_groups',
            'sale_price_id',
            'user_group_id'
        );
    }

    /**
     * @return BelongsToMany
     */
    public function memberLevels()
    {
        return $this->morphToMany(
            MemberLevel::class,
            'service',
            'dsp_sale_prices_user_levels',
            'sale_price_id',
            'user_level_id'
        );
    }

    /**
     * @return BelongsToMany
     */
    public function userTags()
    {
        return $this->morphToMany(
            UserTag::class,
            'service',
            'dsp_sale_prices_user_tags',
            'sale_price_id',
            'user_tag_id'
        );
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeUsable($query)
    {
        return $query->where('enabled', 1)
            ->where('effect_at', '<=', now())
            ->where('expire_at', '>=', now());
    }

    /**
     * @return int
     */
    public function getStatusAttribute(): int
    {
        if (now()->lessThan($this->effect_at)) {
            return 0;
        }

        if (now()->greaterThan($this->expire_at)) {
            return 2;
        }

        return 1;
    }
}
