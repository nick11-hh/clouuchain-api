<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use App\Models\Traits\HasValidateUnique;
use App\Models\Traits\LikeScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CountryArea extends Model
{
    use Basis, HasValidateUnique, LikeScope, CustomHasTranslations;

    public $translatable = ['name'];

    protected $table = 'dsp_country_areas';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    /**
     * @return HasMany
     */
    public function areas(): HasMany
    {
        return $this->hasMany(CountryArea::class, 'parent_id', 'id')
            ->withoutGlobalScope('enabled');
    }

    /**
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(CountryArea::class, 'parent_id', 'id')
            ->withoutGlobalScope('enabled');
    }

    /**
     * 区域自提点
     *
     * @return HasMany
     */
    public function stations()
    {
        return $this->hasMany(SelfPickupStation::class, 'sub_area_id', 'id');
    }

    /**
     * 区域通知
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function notification()
    {
        return $this->hasOne(AreaNotification::class, 'id', 'notification_id');
    }

    /**
     * 所属国家
     *
     * @return BelongsTo
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public static function booted()
    {
        parent::booted();
        //客户端不展示不启用的国家
        if (stripos(request()->path(), 'api/client') === 0) {
            static::addGlobalScope('enabled', function ($builder) {
                $builder->where('enabled', 1);
            });
        }
    }
}
