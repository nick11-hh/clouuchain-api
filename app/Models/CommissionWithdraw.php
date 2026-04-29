<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class CommissionWithdraw extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_commission_withdraws';

    protected $guarded = [];

    protected $casts = [
        'custom_images' => 'array',
        'confirm_images' => 'array',
    ];

    const STATUS_APPLY = 1;
    const STATUS_AUDIT = 2;
    const STATUS_REJECT = 3;

    const WITHDRAW_WAIT = 1;
    const WITHDRAW_SUCCESS = 2;

    const WITHDRAW_TYPE_BALANCE = 1;

    /** 审核状态
     * @return array
     */
    public static function statusList()
    {
        return [
            self::STATUS_APPLY => __('待审核'),
            self::STATUS_AUDIT => __('已审核'),
            self::STATUS_REJECT => __('未通过'),
        ];
    }

    public static function withdrawStatusList()
    {
        return [
            self::WITHDRAW_WAIT => __('待打款'),
            self::WITHDRAW_SUCCESS => __('已打款'),
        ];
    }

    public static function withdrawTypeList()
    {
        return [
            self::WITHDRAW_TYPE_BALANCE => __('余额提现'),
        ];
    }

    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id');
    }

    public function commission()
    {
        return $this->hasMany(AgentCommission::class, 'withdraw_id', 'id');
    }

    public function getStatusNameAttribute()
    {
        return $this->statusList()[$this->status] ?? '-';
    }

    public function getWithdrawStatusNameAttribute()
    {
        return $this->withdrawStatusList()[$this->withdraw_status] ?? '-';
    }

    public function getWithdrawTypeNameAttribute()
    {
        return $this->withdrawTypeList()[$this->withdraw_type] ?? '-';
    }



    public static function init($params)
    {
        return [
            'custom_id' => $params['custom_id'] ?? getCustomId(),
            'serial_no' => $params['serial_no'] ?? self::getSerialNo(),
            'withdraw_type' => $params['withdraw_type'],
            'withdraw_amount' => $params['withdraw_amount'] ?? '',
            'withdraw_account' => $params['withdraw_account'] ?? '',
            'custom_remark' => $params['custom_remark'] ?? '',
            'custom_images' => $params['custom_images'] ?? [],
        ];
    }

    public static function getSerialNo()
    {
        if (Cache::has('withdrawSerialNoCache')) {
            $increment = Cache::increment('withdrawSerialNoCache');
        } else {
            $latest = self::query()->latest('id')->first();
            if (empty($latest->serial_no)) {
                $increment = now()->unix();
            } else {
                $increment = substr($latest->serial_no, -10);
                $increment ++;
            }
            Cache::increment('withdrawSerialNoCache', $increment);
        }
        return 'WD' . date('Ymd') . $increment;
    }
}
