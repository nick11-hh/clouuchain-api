<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpressLinePriceRulesModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_express_line_price_rules';

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
