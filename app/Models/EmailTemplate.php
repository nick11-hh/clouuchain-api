<?php

namespace App\Models;

use App\Models\Traits\Basis;

class EmailTemplate extends Model
{
    use Basis;

    protected $table = 'dsp_email_template';


    public const REGISTER_CHECK = 1;//注册验证
    public const FORGOT_PASSWORD = 2;//忘记密码
    public const INSUFFICIENT_BALANCE = 3;//余额不足
    public const CHANGE_PASSWORD_MSG = 4;//更改密码消息通知
    public const CHANGE_EMAIL = 5;//更改邮箱
    // public const BIND_EMAIL = 6;//绑定邮箱
    public const PENDING_PAYMENT_ORDER = 7;//待支付订单
    public const PRODUCT_QUOTATION = 8;//产品报价
    public const ORDER_QUOTATION = 9;//订单报价
    public const SHIPPING_REMINDER = 10;//发货提醒通知
    public const DELAYED_SHIPMENT = 11;//延迟发货通知


    /**
     * 获得邮件类型
     *
     * @return mixed
     */
    public static function typeList()
    {
        return [
            [
                'id'   => self::REGISTER_CHECK,
                'name' => __('注册验证'),
            ],
            [
                'id'   => self::FORGOT_PASSWORD,
                'name' => __('忘记密码'),
            ],
            [
                'id'   => self::INSUFFICIENT_BALANCE,
                'name' => __('余额不足'),
            ],
            [
                'id'   => self::CHANGE_PASSWORD_MSG,
                'name' => __('更改密码'),
            ],
            [
                'id'   => self::CHANGE_EMAIL,
                'name' => __('更改邮箱'),
            ],
            [
                'id'   => self::PENDING_PAYMENT_ORDER,
                'name' => __('待支付订单'),
            ],
            [
                'id'   => self::PRODUCT_QUOTATION,
                'name' => __('产品报价'),
            ],
            [
                'id'   => self::ORDER_QUOTATION,
                'name' => __('订单报价'),
            ],
            [
                'id' => self::SHIPPING_REMINDER, //sync_waybill_number按照同步运单号设置来发送邮件
                'name' => __('发货提醒'),
            ],
            [
                'id' => self::DELAYED_SHIPMENT, //sync_waybill_number按照同步运单号设置来发送邮件
                'name' => __('延迟发货提醒'),
            ]
        ];
    }

    public function getTypeNameAttribute()
    {
        $type = [
            self::REGISTER_CHECK        => __('注册验证'),
            self::FORGOT_PASSWORD       => __('忘记密码'),
            self::INSUFFICIENT_BALANCE  => __('余额不足'),
            self::CHANGE_PASSWORD_MSG   => __('更改密码'),
            self::CHANGE_EMAIL          => __('更改邮箱'),
            self::PENDING_PAYMENT_ORDER => __('待支付订单'),
            self::PRODUCT_QUOTATION     => __('产品报价'),
            self::ORDER_QUOTATION       => __('订单报价'),
            self::SHIPPING_REMINDER     => __('发货提醒'),
            self::DELAYED_SHIPMENT      => __('延迟发货提醒'),
        ];
        return $type[$this->type] ?? '-';
    }
}
