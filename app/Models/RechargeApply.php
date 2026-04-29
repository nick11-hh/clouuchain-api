<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class RechargeApply extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_recharge_applies';

    protected $guarded = [];

    protected $casts = [
        'apply_images' => 'array',
        'confirm_images' => 'array',
        'check_images' => 'array',
    ];

    public const STATUS_DEFAULT = 0;
    public const STATUS_AUDIT_SUCCESS = 1;
    public const STATUS_AUDIT_FAIL = 2;
    public const STATUS_REVOCATION = 3;

    public const CHECK_STATUS_0 = 0;//未核账
    public const CHECK_STATUS_1 = 1;//已核账

    public static function statusList()
    {
        return [
            self::STATUS_DEFAULT => __('待审核'),
            self::STATUS_AUDIT_SUCCESS => __('审核成功'),
            self::STATUS_AUDIT_FAIL => __('审核失败'),
            self::STATUS_REVOCATION => __('撤销充值'),
        ];
    }

    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id')->withoutGlobalScope('customer_filter');
    }

    public function paymentType()
    {
        return $this->belongsTo(PaymentSetting::class, 'payment_type_id', 'id');
    }

    public function revocationOperator()
    {
        return $this->hasOne(Admin::class, 'id', 'revocation_operator');
    }

    public function payMethod()
    {
        return $this->hasOne(ChargePayMethod::class, 'id', 'pay_method');
    }

    public static function init($params)
    {
        return [
            'custom_id' => $params['custom_id'] ?? getCustomId(),
            'payment_type_id' => $params['payment_type_id'],
            'apply_amount' => $params['apply_amount'],//申请金额 USD
            'apply_images' => $params['apply_images'] ?? [],
            'apply_remark' => $params['apply_remark'] ?? '',
            'pay_account' => $params['pay_account'] ?? '',
            'serial_no' => self::getSerialNo(),
            'out_serial_no' => '',
            'apply_operator' => $params['apply_operator'] ?? auth('client')->id(),
            'pay_amount' => $params['pay_amount'] ?? $params['apply_amount'], //转账支付金额 多币种
            'currency' => $params['currency'] ?? 'USD',//支付币种
            'pay_method' => $params['pay_method'] ?? 0
        ];
    }

    public function getStatusNameAttribute()
    {
        return $this->statusList()[$this->status] ?? '-';
    }

    /**
     * @param $sourceType
     * @return string
     */
    public static function getSerialNo()
    {
        if (Cache::has('RechargeApplyNoCache')) {
            $increment = Cache::increment('RechargeApplyNoCache');
        } else {
            $latest = self::query()->latest('id')->first();
            if (empty($latest)) {
                $increment = now()->unix();
            } else {
                $increment = substr($latest->serial_no, -10);
                $increment ++;
            }
            Cache::increment('RechargeApplyNoCache', $increment);
        }
        return 'RCA' . date('Ymd') . $increment;
    }

    public function getCheckStatusNameAttribute()
    {
        return $this->check_status != self::CHECK_STATUS_1 ? __('未核验') : __('已核验');
    }

    public function getCheckAdminNameAttribute()
    {
        return $this->check_status == self::CHECK_STATUS_1 ? Admin::where('id', $this->check_admin_id)->value('name') : '';
    }
}
