<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class BalanceRecord extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_balance_records';

    protected $guarded = [];

    protected $casts = [
        'attachment_files' => 'array',
    ];
    # 增加或减少
    const CHANGE_INCREASE = 1;
    const CHANGE_DEDUCTION = 2;

    # 变更来源
    const SOURCE_RECHARGE = 1;
    const SOURCE_WITHDRAW = 2;
    const SOURCE_ORDER_PAY = 3;
    const SOURCE_PAYPAL_RECHARGE = 4;
    const SOURCE_COMPLIMENTARY_RECHARGE = 5;
    const SOURCE_ORDER_REFUND = 6;
    const SOURCE_RECHARGE_REVOCATION = 7;
    const SOURCE_SUPPLEMENT_FEE = 8;

    const SOURCE_MANUALLY_DEDUCT = 9;
    const SOURCE_ADJUST_CREDIT_LIMIT = 10;
    const SOURCE_ADJUST_FROZEN_LIMIT = 11;

    const SOURCE_CREDIT_CARD_RECHARGE = 12; // 信用卡充值

    // 每个操作来源关联的数据库模型
    const SOURCE_RELATION_TYPE = [
        self::SOURCE_RECHARGE => RechargeApply::class,
        self::SOURCE_WITHDRAW => CommissionWithdraw::class,
        self::SOURCE_ORDER_PAY => Order::class,
        self::SOURCE_PAYPAL_RECHARGE => BalanceRecharge::class,
        self::SOURCE_COMPLIMENTARY_RECHARGE => BalanceRecharge::class,
        self::SOURCE_ORDER_REFUND => Order::class,
        self::SOURCE_RECHARGE_REVOCATION => RechargeApply::class,
        self::SOURCE_SUPPLEMENT_FEE => Order::class,
        self::SOURCE_MANUALLY_DEDUCT => '',
        self::SOURCE_ADJUST_CREDIT_LIMIT => '',
        self::SOURCE_ADJUST_FROZEN_LIMIT => '',
        self::SOURCE_CREDIT_CARD_RECHARGE=>'',
    ];


    public static function changeType()
    {
        return [
            self::CHANGE_INCREASE => __('增加'),
            self::CHANGE_DEDUCTION => __('减少'),
        ];
    }

    public static function sourceList()
    {
        return [
            self::SOURCE_RECHARGE => __('线下充值'),
            self::SOURCE_CREDIT_CARD_RECHARGE => __('信用卡充值'),
            self::SOURCE_WITHDRAW => __('佣金提现'),
            self::SOURCE_ORDER_PAY => __('订单支付'),
            self::SOURCE_PAYPAL_RECHARGE => __('paypal充值'),
            self::SOURCE_COMPLIMENTARY_RECHARGE => __('充值赠送'),
            self::SOURCE_ORDER_REFUND => __('订单退款'),
            self::SOURCE_RECHARGE_REVOCATION => __('撤销充值'),
            self::SOURCE_SUPPLEMENT_FEE => __('补收费用'),
            self::SOURCE_MANUALLY_DEDUCT => __('后台手动扣款'),
            self::SOURCE_ADJUST_CREDIT_LIMIT => __('调整信用额度'),
            self::SOURCE_ADJUST_FROZEN_LIMIT => __('调整冻结额度')
        ];
    }

    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id');
    }


    public function getTypeNameAttribute()
    {
        return self::changeType()[$this->type] ?? '-';
    }

    public function getSourceTypeNameAttribute()
    {
        return self::sourceList()[$this->source_type] ?? '-';
    }

    public function order()
    {
        return $this->hasOne(Order::class, 'id', 'relation_id')->withTrashed();
    }

    /**
     * @param $sourceType
     * @return string
     */
    public static function getSerialNo($sourceType)
    {
        if (Cache::has('SerialNoCache')) {
            $increment = Cache::increment('SerialNoCache');
        } else {
            $latest = self::query()->latest('id')->first();
            if (empty($latest)) {
                $increment = now()->unix();
            } else {
                // 只取流水号末尾 10 位数字并转为整数，避免包含字母导致 Redis::incrBy 报类型错误
                $increment = (int) substr($latest->serial_no, -10);
                $increment++;
            }
            // 首次写入使用 set，避免在 Redis::incrBy 中传入非法类型
            Cache::set('SerialNoCache', (int) $increment);
        }
        return (self::SERIAL_NO_PRE[$sourceType] ?? 'CO') . date('Ymd') . $increment;
    }

    public function admin()
    {
        return $this->hasOne(Admin::class, 'id', 'operate_admin_id');
    }

    const SERIAL_NO_PRE = [
        self::SOURCE_RECHARGE => 'CH'
    ];

}
