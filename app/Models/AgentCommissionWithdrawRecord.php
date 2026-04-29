<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasStatusName;
use App\Models\Traits\HasValidateUnique;
use App\Services\Client\StringTranslationService;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentCommissionWithdrawRecord extends Model
{
    use Basis,
        HasValidateUnique,
        HasStatusName;

    public const WAIT_CHECK = 0; // 待审核
    public const CHECK_SUCCESS = 1; // 审核通过
    public const CHECK_FAIL = 2; // 审核失败

    public const BALANCE = 1; //提现到余额
    public const WECHAT = 2; //提现到微信
    public const ALIPAY = 3; //提现到支付宝
    public const BANK_CARD = 4; //提现到银行卡
    public const WECHAT_BALANCE = 5; // 提现到微信余额 - 自动转账

    //提现状态1-待提现2-提现中3-提现成功4-提现失败
    public const WAIT_WITHDRAWN = 1;
    public const WITHDRAWING = 2;
    public const WITHDRAWN_PASS = 3;
    public const WITHDRAWN_FAILED = 4;

    protected $table = 'dsp_commission_withdraw_record';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'customer_images' => 'array',
    ];

    protected $appends = [
        'withdraw_type_name',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class, 'user_id', 'agent_id');
    }

    public function commissions()
    {
        return $this->hasMany(AgentCommission::class, 'withdraw_id', 'id');
    }

    public function thirdWithdrawLogs()
    {
        return $this->hasMany(ThirdWithdrawLog::class, 'withdraw_id', 'id');
    }

    /**
     * @return HasMany
     */
    public function wechatTransferRecords(): HasMany
    {
        return $this->hasMany(WechatTransferRecord::class, 'record_id', 'id');
    }

    public static function genSerialNo($userId, $pre = 'COMMISSTION')
    {
        return $pre . $userId . time();
    }

    public function getWithdrawStatusNameAttribute()
    {
        return !empty($this->withdraw_status) ? (self::getWithdrawStatusList()[$this->withdraw_status] ?? '') : '';
    }

    /**
     * @return mixed|string|string[]
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function getWithdrawTypeNameAttribute()
    {
        $name = StringTranslationService::apiTran('余额充值');
        switch ($this->type) {
            case self::BALANCE:
                $name = StringTranslationService::apiTran('余额');
                break;
            case self::WECHAT:
                $name = StringTranslationService::apiTran('微信');
                break;
            case self::ALIPAY:
                $name = StringTranslationService::apiTran('支付宝');
                break;
            case self::BANK_CARD:
                $name = StringTranslationService::apiTran('银行卡');
                break;
            case self::WECHAT_BALANCE:
                $name = StringTranslationService::apiTran('微信余额');
                break;
            default:
                # code...
                break;
        }
        return $name;
    }

    public static function getStatusList()
    {
        return [
            self::WAIT_CHECK => __('待审核'),
            self::CHECK_SUCCESS => __('审核成功'),
            self::CHECK_FAIL => __('审核失败'),
        ];
    }

    public static function getTypeList()
    {
        return [
            self::BALANCE => __('余额'),
            self::WECHAT => __('微信'),
            self::ALIPAY => __('支付宝'),
            self::BANK_CARD => __('银行卡'),
        ];
    }

    public static function getWithdrawTypeList()
    {
        return [
            self::BALANCE => __('余额'),
            self::WECHAT => __('微信'),
            self::ALIPAY => __('支付宝'),
        ];
    }

    public static function getWithdrawStatusList()
    {
        return [
            self::WAIT_WITHDRAWN => __('待提现'),
            self::WITHDRAWING => __('提现中'),
            self::WITHDRAWN_PASS => __('提现成功'),
            self::WITHDRAWN_FAILED => __('提现失败'),
        ];
    }
}
