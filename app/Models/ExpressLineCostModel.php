<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExpressLineCostModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_express_line_costs';

    /**
     * 线路
     *
     * @return BelongsToMany
     */
    public function expressLines(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpressLineModel::class,
            'dsp_express_line_costs_pivot',
            'express_line_cost_id',
            'express_line_id'
        )->withPivot(['price', 'type']);
    }
}
