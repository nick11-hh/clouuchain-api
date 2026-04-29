<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpressLineRegionAreasModel extends Model
{
    use Basis,
        CustomHasTranslations;

    public $translatable = ['country_name', 'area_name', 'sub_area_name'];
    protected $table = 'dsp_express_line_region_areas';

    /**
     * 所属线路
     *
     * @return BelongsTo
     */
    public function expressLine(): BelongsTo
    {
        return $this->belongsTo(ExpressLineModel::class, 'express_line_id', 'id');
    }

    /**
     * 所属分区
     *
     * @return BelongsTo
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(ExpressLineRegion::class, 'region_id', 'id');
    }
}
