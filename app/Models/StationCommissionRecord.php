<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 自提点佣金审核记录表
 * @property int $status
 */
class StationCommissionRecord extends Model
{
    use Basis, HasValidateUnique;

    public const STATUS_UN_VERIFY = 0; //待确认
    public const STATUS_VERIFY = 1; //已结算
    public const APPLY_WITHDRAWN = 2; //申请提现
    public const WITHDRAWN = 3; //已提现

    protected $table = 'dsp_station_commission_records';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'payment_images' => 'array',
    ];

    protected $appends = [];

    /**
     * 佣金
     * @return HasMany
     */
    public function commissions(): HasMany
    {
        return $this->HasMany(StationCommission::class, 'record_id', 'id');
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
     * @return string
     */
    public function getStatusNameAttribute(): string
    {
        switch ($this->status) {
            case self::STATUS_UN_VERIFY:
                return '待确认';
            case self::STATUS_VERIFY:
                return '已结算';
            case self::APPLY_WITHDRAWN:
                return '申请提现';
            case self::WITHDRAWN:
                return '已提现';
        }

        return '';
    }
}
