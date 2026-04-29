<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;

/**
 * 新用户送券模板
 */
class NewUserCouponTemplate extends Model
{
    use Basis,
        CustomHasTranslations;

    //触发条件1-新人支付第一笔订单2-客户注册登陆
    public const TRIGGER_AFTER_ORDER = 1;
    public const TRIGGER_AFTER_REGISTER = 2;

    public const TYPE_AMOUNT = 1;
    public const TYPE_WEIGHT = 2;

    public const EVENT_PAID = 1; // 支付成功
    public const EVENT_SIGNED = 2; // 签收成功
    public const EVENT_COMMENTED = 3; // 评价成功

    public $translatable = ['name'];

    protected $table = 'dsp_new_user_coupon_templates';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'express_line_ids' => 'array',
        'trigger_condition' => 'int'
    ];

}
