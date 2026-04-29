<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpressLinePrice extends Model
{
    use Basis,
        HasValidateUnique;

    public const TYPE_FIRST_WEIGHT = 0;//首重续重模式-首费
    public const TYPE_NEXT_WEIGHT = 1;//首重续重模式-续单价
    public const TYPE_GRADE_WEIGHT = 2;//阶梯价格模式-单价
    public const TYPE_UNIT_WEIGHT = 3;
    public const TYPE_GRADE_WEIGHT_APPEND = 4;
    public const TYPE_GRADE_NEXT_WEIGHT = 5;
    public const TYPE_RANGE_FIRST_WEIGHT = 6;//阶梯首重续重模式-首费
    public const TYPE_RANGE_NEXT_WEIGHT = 7;//阶梯首重续重模式-单价
    public const TYPE_GRADE_WEIGHT_BASE = 8;//阶梯价格模式-基价/操作费

    protected $table = 'dsp_express_line_prices';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'price' => 'array',
    ];

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
     * 所属区域
     *
     * @return BelongsTo
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(ExpressLineRegion::class, 'region_id', 'id');
    }
}
