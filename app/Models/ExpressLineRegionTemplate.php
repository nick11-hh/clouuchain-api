<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ExpressLineRegionTemplate extends Model
{
    use Basis,
        HasValidateUnique,
        CustomHasTranslations;

    public const TYPE_AREA = 1;
    public const TYPE_POSTCODE = 2;

    public $translatable = ['name', 'reference_time'];

    protected $table = 'dsp_express_line_region_templates';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    /**
     * 分区下属地理区域
     *
     * @return HasMany
     */
    public function areas(): HasMany
    {
        return $this->hasMany(ExpressLineRegionTemplateArea::class, 'region_id', 'id');
    }

    /**
     * 分区下属地邮编区域
     *
     * @return HasMany
     */
    public function postcodeAreas(): HasMany
    {
        return $this->hasMany(ExpressLineRegionTemplatePostcodeArea::class, 'region_id', 'id');
    }

    /**
     * @return HasOne
     */
    public function country(): HasOne
    {
        return $this->hasOne(Country::class, 'id', 'country_id');
    }
}
