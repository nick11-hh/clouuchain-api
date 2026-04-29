<?php

namespace App\Models;

use App\Exceptions\AccidentException;
use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 自提点佣金分成表
 */
class StationCommission extends Model
{
    use Basis, HasValidateUnique, SoftDeletes;

    public const STATUS_UN_VERIFY = 0; //待确认
    public const STATUS_VERIFY = 1; //可申请提现
    public const APPLY_WITHDRAWN = 2; //已经申请提现
    public const WITHDRAWN = 3; //已提现

    protected $table = 'dsp_station_commissions';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    /**
     * 佣金订单
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * 自提点
     * @return BelongsTo
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(SelfPickupStation::class, 'station_id', 'id');
    }

    /**
     * 自提点
     * @return mixed
     */
    public function stationOrder()
    {
        return $this->belongsTo(StationOrder::class, 'order_id', 'order_id');
    }
}
