<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int allow_all_order
 * @property int notify_after_received
 */
class SelfPickupStation extends Model
{
    use Basis,
        CustomHasTranslations;

    protected $table = 'dsp_self_pickup_stations';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    public $translatable = [
        'name', 'opening_hours', 'announcement', 'store_time', 'remark', 'overdue_fee',
    ];

    /**
     * 支持的国家
     * @return HasOne
     */
    public function country(): HasOne
    {
        return $this->hasOne(Country::class, 'id', 'country_id');
    }

    /**
     * 支持的国家
     * @return HasOne
     */
    public function area(): HasOne
    {
        return $this->hasOne(CountryArea::class, 'id', 'area_id');
    }

    /**
     * 支持的国家
     * @return HasOne
     */
    public function subArea(): HasOne
    {
        return $this->hasOne(CountryArea::class, 'id', 'sub_area_id');
    }

    /**
     * 自提点支持的仓库
     *
     * @return BelongsToMany
     */
    public function expressLines(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpressLineModel::class,
            'dsp_self_pickup_station_expresslines',
            'station_id',
            'express_line_id'
        );
    }

    /**
     * 计佣金规则
     *
     * @return HasOne
     */
    public function rule(): HasOne
    {
        return $this->hasOne(StationRule::class, 'id', 'rule_id');
    }

    /**
     * 支付方式
     * @return HasOne
     */
    public function payment(): HasOne
    {
        return $this->hasOne(PaymentSetting::class, 'id', 'payment_type');
    }

    /**
     * 佣金审核记录
     *
     * @return HasMany
     */
    public function commissionRecords(): HasMany
    {
        return $this->hasMany(StationCommissionRecord::class, 'station_id', 'id');
    }

    /**
     * @return string
     */
    public function getCountryNameAttribute(): string
    {
        $name = $this->country->name;

        if ($area = $this->area) {
            $name .= $area->name;
        }

        if ($subArea = $this->subArea) {
            $name .= $subArea->name;
        }

        return $name;
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
