<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegionTemplate extends Model
{
    use Basis,
        HasValidateUnique,
        CustomHasTranslations;

    public $translatable = ['name'];

    protected $table = 'dsp_region_templates';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    /**
     * 模板下的多个分区
     *
     * @return HasMany
     */
    public function regions(): HasMany
    {
        return $this->hasMany(ExpressLineRegionTemplate::class, 'template_id', 'id');
    }
}
