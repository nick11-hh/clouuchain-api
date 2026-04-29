<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentSetting extends Model
{
    use Basis,
        HasValidateUnique,
        CustomHasTranslations,
        HasFactory,
        SoftDeletes;

    protected $table = 'dsp_payment_settings';
    //用于翻译
    public $translatable = ['name', 'remark'];

    protected $guarded = [];

    protected $casts = [];

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;

    public static function getEnableStatus()
    {
        return [
          self::STATUS_DISABLED => __('禁用'),
          self::STATUS_ENABLED => __('启用'),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function PaymentSettingConnection()
    {
        return $this->hasMany(PaymentSettingConnection::class, 'payment_settings_id', 'id');
    }

    public function getEnabledNameAttribute()
    {
        return self::getEnableStatus()[$this->enabled] ?? '-';
    }

    public static function init($params)
    {
        return [
            'name' => $params['name'],
            'pay_logo' => $params['pay_logo'],
            'pay_qrcode' => $params['pay_qrcode'] ?? '',
            'pay_account' => $params['pay_account'] ?? '',
            'remark' => $params['remark'] ?? '',
            'enabled' => $params['enabled'] ?? 1,
            'currency' => $params['currency'] ?? ''
        ];
    }
}
