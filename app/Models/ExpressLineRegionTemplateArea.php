<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpressLineRegionTemplateArea extends Model
{
    use Basis,
        CustomHasTranslations;

    public $translatable = ['country_name', 'area_name', 'sub_area_name'];

    protected $table = 'dsp_express_line_region_template_areas';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    /**
     * 所属分区
     *
     * @return BelongsTo
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(ExpressLineRegionTemplate::class, 'region_id', 'id');
    }

    /**
     * @return BelongsTo
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    /**
     * @return BelongsTo
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(CountryArea::class, 'area_id', 'id');
    }

    /**
     * @return BelongsTo
     */
    public function subArea(): BelongsTo
    {
        return $this->belongsTo(CountryArea::class, 'sub_area_id', 'id');
    }
}
