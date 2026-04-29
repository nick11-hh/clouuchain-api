<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpressLinePriceGradeModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_express_line_price_grade';

    /**
     * 支持的国家
     * @return BelongsTo
     */
    public function expressLine(): BelongsTo
    {
        return $this->belongsTo(
            ExpressLineModel::class,
            'express_line_id',
            'id'
        );
    }
}
