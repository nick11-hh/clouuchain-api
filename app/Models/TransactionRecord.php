<?php

namespace App\Models;

use App\Models\Scope\CompanyScope;
use App\Models\Traits\Basis;
use App\Models\Traits\LikeScope;
use App\Models\Traits\UserFilter;
use App\Services\Client\StringTranslationService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * 流水表
 */
class TransactionRecord extends Model
{
    use Basis, LikeScope, UserFilter;

    //流水类型
    public const PAY = 1;  // 支付
    public const RECHARGE = 2;  //充值
    public const REFUND = 3;  //退款
    public const WITHDRAW = 4; //提现
    public const DEDUCT = 5; //后台扣款
    public const COMPLIMENTARY_RECHARGE = 6; //充值赠送
    public const GROWTH_BUY = 7; //成长值消费
    public const ADDITIONAL_FEE = 8; //订单补款
    public const LUCKY_DRAW = 9; // 抽奖

    //支付方式 mod4pay
    public const OTHERS = 100;
    public const OTTPAY_WECHAT = 7;  // OTTPAY 微信支付
    public const OMIPAY_WECHAT = 8;  // OMIPAY 微信支付
    public const IOTPAY_WECHAT = 9;  // OMIPAY 微信支付
    public const PAYMENT_ASIA = 10;  // PaymentAsia支付
    public const ALIPAY = 6;  // 支付宝支付
    public const PAY_ON_DELIVERY = 5;  // 货到付款
    public const PAYPAL = 4;  // Paypal支付
    public const TRANSFER = 2;  // 转账支付
    public const BALANCE = 1;  // 余额支付
    public const WECHAT = 0;    //微信支付
    public const ALLINPAY = 15;  // ALLINPAY
    public const MERGE = 16;  // 合并客户
    public const ROYAL_PAY_WECHAT = 17;  // ROYAL_PAY 微信支付
    public const LATIPAY_WECHAT = 18;  // ROYAL_PAY 微信支付
    public const IPay88 = 19;  // IPay88
    public const QFPAY = 20;  // QFPAY
    public const HANTE_PAY = 21;  // HanTePay
    public const ECPAY = 22;  // ECPay

    public const COMMISSION_WITHDRAW = 10001; //佣金提现余额
    public const COMMISSION_WITHDRAW_THIRD = 10002; //佣金提现第三方

    public const STATION_COMMISSION_WITHDRAW_THIRD = 10003; //自提点佣金提现

    protected $table = 'jiyun_transaction_record';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [
        'pay_name',
    ];

    /**
     * @return array
     */
    public static function getTypeNames()
    {
        return [
            self::PAY => __('消费'),
            self::RECHARGE => __('充值'),
            self::REFUND => __('退款'),
            self::WITHDRAW => __('提现'),
            self::DEDUCT => __('扣款'),
            self::COMPLIMENTARY_RECHARGE => __('充值赠送'),
            self::ADDITIONAL_FEE => __('补款'),
            self::LUCKY_DRAW => __('抽奖活动'),
        ];
    }

    /**
     * 用户
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 订单
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_sn', 'order_sn');
    }

    /**
     * 代购订单
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function daiGouOrder()
    {
        return $this->belongsTo(DaiGouOrder::class, 'order_sn', 'order_sn');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function paymentSetting()
    {
        return $this->hasOne(PaymentSetting::class, 'id', 'mod4pay');
    }

    public function recordResource()
    {
        if($this->type == self::PAY){
            //若是收支,则匹配收支
            if(Str::startsWith('IOR', $this->serial_no)){
                return $this->hasOne(IncomeOutlayRecord::class,'serial_no','serial_no')->selectRaw('id,serial_no,customer_remark AS remark');
            }
            //若是代购订单,则匹配代购订单
            if(Str::startsWith('DG', $this->serial_no)){
                return $this->hasOne(DaiGouOrderTransferPayInfo::class,'serial_no','serial_no')->selectRaw('id,serial_no,customer_remark AS remark');
            }
            //否则匹配订单
            $recource =  $this->hasOne(TransferPayInfo::class,'serial_no', 'serial_no')->selectRaw('id,serial_no,customer_remark AS remark');
            return $recource;
        };

        return match($this->type){
            self::DEDUCT => $this->hasOne(DeductMoney::class,'order_sn', 'order_sn'),
            self::REFUND => $this->hasOne(OrderRefundRecord::class,'order_sn','order_sn'),
            self::RECHARGE => $this->hasOne(BalanceRechargeRecord::class,'serial_no','serial_no')->selectRaw('id,serial_no,customer_remark AS remark'),
            default => $this->hasOne(self::class,'order_sn','order_sn')
        };
    }

    /**
     * @return array|string|null
     */
    public function getTypeNameAttribute()
    {
        return match ($this->type) {
            self::PAY => __('消费'),
            self::RECHARGE => __('充值'),
            self::REFUND => __('退款'),
            self::WITHDRAW => __('提现'),
            self::DEDUCT => __('扣款'),
            self::COMPLIMENTARY_RECHARGE => __('充值赠送'),
            default => '',
        };
    }

    public function getPayNameAttribute()
    {
        if ($this->mod4pay === self::WECHAT) {
            return StringTranslationService::apiTran('微信支付');
        }

        if ($this->mod4pay === self::BALANCE) {
            return StringTranslationService::apiTran('余额支付');
        }

        if ($this->mod4pay === self::PAYPAL) {
            return StringTranslationService::apiTran('Paypal支付');
        }

        if ($this->mod4pay === self::PAY_ON_DELIVERY) {
            return StringTranslationService::apiTran('货到付款');
        }

        if ($this->mod4pay === self::ALIPAY) {
            return StringTranslationService::apiTran('支付宝支付');
        }

        if ($this->mod4pay === self::OTTPAY_WECHAT) {
            return StringTranslationService::apiTran('OTTPAY');
        }

        if ($this->mod4pay === self::OMIPAY_WECHAT) {
            return StringTranslationService::apiTran('OMIPAY');
        }

        if ($this->mod4pay === self::IOTPAY_WECHAT) {
            return StringTranslationService::apiTran('IOTPAY');
        }

        if ($this->mod4pay === self::PAYMENT_ASIA) {
            return StringTranslationService::apiTran('PaymentAsia Wechat');
        }

        if ($this->mod4pay === self::ALLINPAY) {
            return StringTranslationService::apiTran('通华收银宝');
        }

        if ($this->mod4pay === self::LATIPAY_WECHAT) {
            return StringTranslationService::apiTran('LaTiPay Wechat');
        }

        if ($this->mod4pay === self::IPay88) {
            return StringTranslationService::apiTran('iPay88');
        }

        if ($this->mod4pay === self::QFPAY) {
            return StringTranslationService::apiTran('QfPay');
        }

        if ($this->mod4pay === self::HANTE_PAY) {
            return StringTranslationService::apiTran('HantePay');
        }

        if ($this->mod4pay === self::ECPAY) {
            return StringTranslationService::apiTran('ECPAY');
        }

        if ($this->mod4pay === self::DEDUCT) {
            return StringTranslationService::apiTran('扣款');
        }

        switch ($this->mod4pay) {
            case self::COMMISSION_WITHDRAW:
                return StringTranslationService::apiTran('代理佣金余额提现');
            case self::COMMISSION_WITHDRAW_THIRD:
                return StringTranslationService::apiTran('代理佣金第三方提现');
            case self::STATION_COMMISSION_WITHDRAW_THIRD:
                return StringTranslationService::apiTran('自提点佣金提现');
        }

        if ($this->paymentSetting) {
            return $this->paymentSetting->name;
        }

        return '';
    }

    public static function getMod4PayNameOnline($mod4pay)
    {
        if ($mod4pay === self::WECHAT) {
            return StringTranslationService::apiTran('微信支付');
        }

        if ($mod4pay === self::BALANCE) {
            return StringTranslationService::apiTran('余额支付');
        }

        if ($mod4pay === self::PAYPAL) {
            return StringTranslationService::apiTran('Paypal支付');
        }

        if ($mod4pay === self::PAY_ON_DELIVERY) {
            return StringTranslationService::apiTran('货到付款');
        }

        if ($mod4pay === self::ALIPAY) {
            return StringTranslationService::apiTran('支付宝支付');
        }

        if ($mod4pay === self::OTTPAY_WECHAT) {
            return StringTranslationService::apiTran('OTTPAY');
        }

        if ($mod4pay === self::OMIPAY_WECHAT) {
            return StringTranslationService::apiTran('OMIPAY');
        }

        if ($mod4pay === self::IOTPAY_WECHAT) {
            return StringTranslationService::apiTran('IOTPAY');
        }

        if ($mod4pay === self::PAYMENT_ASIA) {
            return StringTranslationService::apiTran('PaymentAsia Wechat');
        }

        if ($mod4pay === self::ALLINPAY) {
            return StringTranslationService::apiTran('通华收银宝');
        }

        if ($mod4pay === self::IPay88) {
            return StringTranslationService::apiTran('iPay88');
        }

        if ($mod4pay === self::QFPAY) {
            return StringTranslationService::apiTran('QfPay');
        }

        if ($mod4pay === self::HANTE_PAY) {
            return StringTranslationService::apiTran('HantePay');
        }

        if ($mod4pay === self::ECPAY) {
            return StringTranslationService::apiTran('ECPAY');
        }

        if ($mod4pay === self::LATIPAY_WECHAT) {
            return StringTranslationService::apiTran('LaTiPay Wechat');
        }

        switch ($mod4pay) {
            case self::COMMISSION_WITHDRAW:
                return StringTranslationService::apiTran('代理佣金余额提现');
            case self::COMMISSION_WITHDRAW_THIRD:
                return StringTranslationService::apiTran('代理佣金第三方提现');
            case self::STATION_COMMISSION_WITHDRAW_THIRD:
                return StringTranslationService::apiTran('自提点佣金提现');
        }

        return $mod4pay;
    }

    public function getLocalPayNameAttribute()
    {
        if ($this->mod4pay === self::WECHAT) {
            return __('微信支付');
        }

        if ($this->mod4pay === self::BALANCE) {
            return __('余额支付');
        }

        if ($this->mod4pay === self::PAYPAL) {
            return __('Paypal支付');
        }

        if ($this->mod4pay === self::PAY_ON_DELIVERY) {
            return __('货到付款');
        }

        if ($this->mod4pay === self::ALIPAY) {
            return __('支付宝支付');
        }

        if ($this->mod4pay === self::OTTPAY_WECHAT) {
            return __('OTTPAY');
        }

        if ($this->mod4pay === self::OMIPAY_WECHAT) {
            return __('OMIPAY');
        }

        if ($this->mod4pay === self::IOTPAY_WECHAT) {
            return __('IOTPAY');
        }

        if ($this->mod4pay === self::PAYMENT_ASIA) {
            return __('PaymentAsia Wechat');
        }

        if ($this->mod4pay === self::ALLINPAY) {
            return __('通华收银宝');
        }

        if ($this->mod4pay === self::LATIPAY_WECHAT) {
            return __('LaTiPay Wechat');
        }

        if ($this->mod4pay === self::IPay88) {
            return __('iPay88');
        }

        if ($this->mod4pay === self::QFPAY) {
            return __('QfPay');
        }

        if ($this->mod4pay === self::HANTE_PAY) {
            return __('HantePay');
        }

        if ($this->mod4pay === self::ECPAY) {
            return __('ECPAY');
        }

        switch ($this->mod4pay) {
            case self::COMMISSION_WITHDRAW:
                return __('代理佣金余额提现');
            case self::COMMISSION_WITHDRAW_THIRD:
                return __('代理佣金第三方提现');
            case self::STATION_COMMISSION_WITHDRAW_THIRD:
                return __('自提点佣金提现');
        }

        if ($this->paymentSetting) {
            return $this->paymentSetting->name;
        }

        return '';
    }

    public function getAmountAttribute($value)
    {
        if (!empty($this->out_serial_no)) {
            return $value;
        }
        if ($this->mod4pay == self::BALANCE) {
            return $value;
        }
        if ($value === 0) {
            return $value;
        }
        return (int)bcsub($value, $this->point_amount ?? '0');
    }

    public function showTransAmount()
    {
        return $this->mod4pay > self::COMMISSION_WITHDRAW_THIRD
            && bccomp($this->trans_rate, 1, 4) !== 0;
    }

    /**
     * @return bool
     */
    public function isIncome()
    {
        return in_array($this->type, [self::RECHARGE, self::PAY, self::ADDITIONAL_FEE, self::DEDUCT, self::GROWTH_BUY]);
    }

    protected static function boot()
    {
        static::bootTraits();

        static::addGlobalScope(new CompanyScope());

        static::creating(function ($model) {
            /**
             * @params Model $model
             */
            if (in_array('company_id', Schema::getColumnListing($model->getTable()))) {
                if ($model->company_id === null) {
                    $model->company_id = self::getCompanyId();
                }
            }

            if ($model->currency === null) {
                $model->currency = Currency::code();
                $model->rate = ExchangeRate::currentRate() ?: 1;
            }
        });

        static::created(function (self $transactionRecord){
            //只有真正的消费才处理
            if(in_array($transactionRecord->type, [TransactionRecord::PAY, TransactionRecord::DEDUCT, TransactionRecord::ADDITIONAL_FEE])){
                User::query()->where('id', $transactionRecord->user_id)->increment('consume_amount', $transactionRecord->amount);
            }
        });
    }
}
