<?php

namespace App\Models;

use App\Models\Traits\Basis;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $type
 */
class ExpressLineRegionPostcodeArea extends Model
{
    use Basis;

    public const TYPE_RANGE = 1; // 邮编范围
    public const TYPE_FIXED = 2; // 固定邮编

    public const CANADA_COUNTRY_ID = 179; //加拿大国家id

    protected $table = 'dsp_express_line_region_postcode_areas';

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
