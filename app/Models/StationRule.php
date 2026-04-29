<?php

namespace App\Models;

use App\Models\Traits\Basis;

class StationRule extends Model
{
    use Basis;

    public const TYPE_AMOUNT = 1;   //固定金额
    public const TYPE_WEIGHT = 2;   //按重量计算

    protected $table = 'dsp_station_rules';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'rule' => 'array',
    ];
}
