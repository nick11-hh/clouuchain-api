<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WechatTransferRecord extends Model
{
    use Basis,
        HasValidateUnique;

    public const STATUS_PENDING = 0; // 等待結果
    public const STATUS_SUCCESS = 1; // 通过
    public const STATUS_FAILED = 2; // 失败
    public const STATUS_WAIT_PAY = 3; // 等待支付

    protected $table = 'dsp_wechat_transfer_records';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'data' => 'json',
    ];

    protected $appends = [];

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * @return BelongsTo
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'user_id', 'agent_id');
    }

    /**
     * @return BelongsTo
     */
    public function withdrawRecords(): BelongsTo
    {
        return $this->belongsTo(AgentCommissionWithdrawRecord::class, 'record_id', 'id');
    }

    /**
     * @param $status
     * @return string
     */
    public static array $statusName = [
        self::STATUS_PENDING => '等待转账',
        self::STATUS_SUCCESS => '转账完成',
        self::STATUS_FAILED => '转账失败',
        self::STATUS_WAIT_PAY => '等待支付',
    ];

    /**
     * @return string
     */
    public function getStatusNameAttribute(): string
    {
        return self::$statusName[$this->status] ?? '';
    }
}
