<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpressLinePricesModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_express_line_prices';

    public const TYPE_FIRST_WEIGHT = 0;
    public const TYPE_NEXT_WEIGHT = 1;
    public const TYPE_GRADE_WEIGHT = 2;
    public const TYPE_UNIT_WEIGHT = 3;
    public const TYPE_GRADE_WEIGHT_APPEND = 4;
    public const TYPE_GRADE_NEXT_WEIGHT = 5;
    public const TYPE_RANGE_FIRST_WEIGHT = 6;
    public const TYPE_RANGE_NEXT_WEIGHT = 7;
    public const TYPE_GRADE_WEIGHT_BASE = 8;

    /**
     * 所属线路
     * @return BelongsTo
     */
    public function expressLine(): BelongsTo
    {
        return $this->belongsTo(ExpressLineModel::class, 'express_line_id', 'id');
    }

    /**
     * 所属区域
     *
     * @return BelongsTo
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(ExpressLineRegionModel::class, 'region_id', 'id');
    }
}
