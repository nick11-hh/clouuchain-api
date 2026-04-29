<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $type 分区类型
 */
class ExpressLineRegion extends Model
{
    use Basis,
        HasValidateUnique,
        CustomHasTranslations;

    public const TYPE_AREA = 1;
    public const TYPE_POSTCODE = 2;

    public $translatable = ['name', 'reference_time'];

    protected $table = 'dsp_express_line_regions';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    /**
     * 所属线路
     *
     * @return BelongsTo
     */
    public function expressLine(): BelongsTo
    {
        return $this->belongsTo(ExpressLineModel::class, 'express_line_id', 'id')
            ->withoutGlobalScope('hide');
    }

    /**
     * 分区下属地理区域
     *
     * @return HasMany
     */
    public function areas(): HasMany
    {
        return $this->hasMany(ExpressLineRegionAreasModel::class, 'region_id', 'id');
    }

    /**
     * 分区下属地理区域
     *
     * @return HasMany
     */
    public function postcodeAreas()
    {
        return $this->hasMany(ExpressLineRegionPostcodeAreaModel::class, 'region_id', 'id');
    }

    /**
     * 邮编分区所属国家
     *
     * @return HasOne
     */
    public function country(): HasOne
    {
        return $this->hasOne(Country::class, 'id', 'country_id');
    }

    /**
     * 价格
     *
     * @return HasMany
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ExpressLinePricesModel::class, 'region_id', 'id');
    }

    /**
     * 增值服务价格
     *
     * @return HasMany
     */
    public function servicePrices(): HasMany
    {
        return $this->hasMany(ExpressLineServicePrice::class, 'region_id', 'id');
    }

    /**
     * 区域关联的渠道规则
     *
     * @return BelongsToMany
     */
    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpressLinePriceRulesModel::class,
            'dsp_express_line_rules_regions',
            'region_id',
            'rule_id'
        );
    }

    /**
     * 分区对应的重量区间
     *
     * @return HasMany
     */
    public function priceRules(): HasMany
    {
        return $this->hasMany(ExpressLinePriceRulesModel::class, 'region_id', 'id');
    }

}
