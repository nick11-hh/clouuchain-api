<?php

namespace App\Models;

use App\Models\Traits\CustomerFilter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Custom extends Model
{
    use HasFactory;
    use SoftDeletes;
    use CustomerFilter;

    protected $table = 'dsp_customs';

    protected $guarded = [];

    const STATUS_ENABLE = 1;
    const STATUS_DISABLE = 0;

    const GOODS_ONCE_PRICE_ENABLE = 1;
    const GOODS_ONCE_PRICE_DISABLE = 0;

    const AUTO_PAYMENT_ENABLE = 1;
    const AUTO_PAYMENT_DISABLE = 0;

    public function customGroup()
    {
        return $this->belongsTo(CustomGroup::class, 'group_id', 'id')->withTrashed();
    }

    public function inviter()
    {
        return $this->belongsTo(self::class, 'invite_id', 'id');
    }

    public function promotion()
    {
        return $this->hasMany(self::class, 'invite_id', 'id');
    }

    public function agent()
    {
        return $this->hasMany(AgentCommission::class, 'agent_id', 'id');
    }

    public function mainUser()
    {
        return $this->hasOne(User::class, 'id', 'main_user_id');
    }

    public function balance()
    {
        return $this->hasOne(CustomBalance::class, 'custom_id', 'id');
    }

    public function config()
    {
        return $this->hasOne(CustomConfig::class, 'custom_id', 'id');
    }

    public function customsQuoteConfig()
    {
        return $this->hasOne(CustomsQuoteConfig::class, 'customer_id', 'id');
    }

    public function shopList()
    {
        return $this->hasMany(ShopModel::class, 'customer_id', 'id')->withCount('order');
    }

    public function getStatusNameAttribute()
    {
        return $this->statusList()[$this->status] ?? '-';
    }

    /** 客户状态
     * @return array
     */
    public static function statusList()
    {
        return [
            self::STATUS_DISABLE => __('已注销'),
            self::STATUS_ENABLE => __('启用'),
        ];
    }

    public function staff()
    {
        return $this->hasOne(Admin::class, 'id', 'staff_id');
    }

    public function invoiceAddress()
    {
        return $this->hasOne(CustomInvoiceAddressModel::class, 'customer_id', 'id');
    }

    public function assignDataPermissions()
    {
        return $this->hasMany(AssignDataPermission::class, 'permission_id', 'id')->where('permission_type', 'customer');
    }

    public function oauthClients()
    {
        return $this->hasOne(OauthClients::class, 'user_id', 'id');
    }

    /**
     * 初始化户注册数据
     * @param array $params
     * @param Custom|null $inviteCustom
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/10/24 15:16
     */
    public static function init(array $params, ?Custom $inviteCustom): array
    {
        $data = [
            'custom_name' => $params['company_name'] ?? $params['username'],
            'custom_phone' => $params['phone'] ?? '',
            'phone_area_code' => $params['phone_area_code'] ?? '',
            'custom_email' => $params['email'],
            'status' => Custom::STATUS_ENABLE,
            'group_id' => $params['group_id'] ?? 0,
            'commission_rate' => 1,
            'staff_id' => $params['staff_id'] ?? 0,
            'remark' => $params['remark'] ?? '',
            'default_language' => $params['default_language'] ?? 'zh_CN',
            'customer_number' => $params['customer_number'] ?? '',
            'goods_once_price' => $params['goods_once_price'] ?? 1,
            'country_code' => $params['country_code'] ?? '',
        ];

        $data['invite_id'] = $inviteCustom->id ?? 0;

        return $data;
    }

    public static array $goodsOncePriceStatus = [
        self::GOODS_ONCE_PRICE_ENABLE  => '开启',
        self::GOODS_ONCE_PRICE_DISABLE  => '关闭',
    ];

    public static array $autoPaymentStatus = [
        self::AUTO_PAYMENT_ENABLE  => '开启',
        self::AUTO_PAYMENT_DISABLE  => '关闭',
    ];
}
