<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentCommission extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_agent_commissions';

    protected $guarded = [];

    protected $casts = [];

    const STATUS_NOT_WITHDRAW = 1;
    const STATUS_WITHDRAWING = 2;
    const STATUS_WITHDRAW_SUCCESS = 3;

    public static function getStatusList()
    {
        return [
            self::STATUS_NOT_WITHDRAW => __('未提现'),
            self::STATUS_WITHDRAWING => __('提现中'),
            self::STATUS_WITHDRAW_SUCCESS => __('已提现')
        ];
    }

    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id');
    }

    public function getStatusNameAttribute()
    {
        return $this->getStatusList()[$this->status] ?? '-';
    }

}
