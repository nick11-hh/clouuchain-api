<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;


/**
 * 收支规则表
 *
 * Class AboutUs
 * @package App\Models
 */
class IncomeOutlayRule extends Model
{
    use Basis, HasValidateUnique;

    protected $table = 'dsp_income_outlay_rules';

    //来源类型1-成长值2-积分
    const RESOURCE_TYPE_GROWTH = 1;
    const RESOURCE_TYPE_POINT = 2;

    //收支类型1-收入2-支出
    const TYPE_INCOME = 1;
    const TYPE_OUTLAY = 2;

    //收支规则
    const RULE_CODE_GROWTH_INCREASE = 'GROWTH_INCREASE';    //消费积累成长值
    const RULE_CODE_GROWTH_BUY_INCREASE = 'GROWTH_BUY';  //成长值购买
    const RULE_CODE_GROWTH_INVITE_INCREASE = 'GROWTH_INVITE';  //成长值-新用户邀请
    const RULE_CODE_POINT_INCREASE = 'POINT_INCREASE'; //消费积累积分
    const RULE_CODE_COMMENT_POINT_INCREASE = 'COMMENT_POINT_INCREASE'; //评价积累积分
    const RULE_CODE_POINT_DECREASE = 'POINT_DECREASE';  //积分抵扣消费
    const RULE_CODE_RECHARGE = 'RECHARGE';  //充值
    const RULE_CODE_DECREASE = 'DECREASE';  //抵扣
    const RULE_CODE_QA_POINT_INCREASE = 'QA_POINT_INCREASE'; //问答积累积分
    const RULE_CODE_PACKAGE_IN_STORAGE_POINT_INCREASE = 'PIS_POINT_INCREASE'; //包裹预报入库获得积分
    const RULE_CODE_WECHAT_AUTH_POINT_INCREASE = 'WA_POINT_INCREASE'; //首次设置微信认证
    const RULE_CODE_RECHARGE_POINT_INCREASE = 'RECHARGE_POINT_INCREASE'; //充值送积分
    const RULE_CODE_OA_POINT_INCREASE = 'OA_POINT_INCREASE'; //首次关注公众号送积分
    const RULE_CODE_BIND_PHONE_POINT_INCREASE = 'BP_POINT_INCREASE'; //首次绑定手机号送积分
    const RULE_CODE_BIND_EMAIL_POINT_INCREASE = 'BE_POINT_INCREASE'; //首次绑定邮箱送积分
    const RULE_CODE_POINT_LUCKY_DRAW = 'POINT_LUCKY_DRAW'; // 抽奖活动送积分
    const RULE_CODE_POINT_LUCKY_DRAW_CONSUME = 'POINT_LUCKY_DRAW_CONSUME'; // 参与抽奖活动消耗积分
    const RULE_CODE_POINT_USER_PROFILE_INCREASE = 'POINT_UP_INCREASE'; // 完善信息送积分
    const RULE_CODE_POINT_POINT_SHOP_DECREASE = 'POINT_SHOP_DECREASE'; // 积分商城消耗
    const RULE_CODE_POINT_POINT_SHOP_RETURN = 'POINT_SHOP_RETURN'; // 积分商城退回
    const RULE_CODE_POINT_POINTS_ORDERING = 'POINT_ORDERING'; // 下单累计积分

    //费用
    const TYPE_FREIGHT_FEE = 'freight_fee';//运费
    const TYPE_INSURANCE_FEE = 'insurance_fee';//保费
    const TYPE_TARIFF_FEE = 'tariff_fee';//关税费
    const TYPE_VALUE_ADDED_AMOUNT = 'value_added_amount';//增值费
    const TYPE_LINE_SERVICE_FEE = 'line_service_fee';//线路服务费
    const TYPE_LINE_RULE_FEE = 'line_rule_fee';//线路规则费
    const TYPE_PACKAGE_SERVICE_FEE = 'package_service_fee';//包裹增值服务费

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];


    public static function getResourceTypeList()
    {
        return [
            self::RESOURCE_TYPE_GROWTH => __('成长值'),
            self::RESOURCE_TYPE_POINT => __('积分')
        ];
    }

    public static function getTypeList()
    {
        return [
            self::TYPE_INCOME => __('收入'),
            self::TYPE_OUTLAY => __('支出'),
        ];
    }

    public static function getRuleList()
    {
        return [
            self::RULE_CODE_GROWTH_INCREASE => __('消费积累成长值'),
            self::RULE_CODE_GROWTH_BUY_INCREASE => __('成长值购买'),
            self::RULE_CODE_POINT_INCREASE => __('消费积累积分'),
            self::RULE_CODE_COMMENT_POINT_INCREASE => __('评价积累积分'),
            self::RULE_CODE_POINT_DECREASE => __('积分抵扣消费'),
            self::RULE_CODE_RECHARGE => __('充值'),
            self::RULE_CODE_DECREASE => __('抵扣'),
            self::RULE_CODE_QA_POINT_INCREASE => __('问答积累积分'),
            self::RULE_CODE_GROWTH_INVITE_INCREASE => __('邀请新用户积累成长值'),
            self::RULE_CODE_PACKAGE_IN_STORAGE_POINT_INCREASE => __('包裹预报入库获得积分'),
            self::RULE_CODE_WECHAT_AUTH_POINT_INCREASE => __('首次设置微信认证'),
            self::RULE_CODE_RECHARGE_POINT_INCREASE => __('余额充值送积分'),
            self::RULE_CODE_OA_POINT_INCREASE => __('首次关注公众号送积分'),
            self::RULE_CODE_BIND_PHONE_POINT_INCREASE => __('首次绑定手机号送积分'),
            self::RULE_CODE_BIND_EMAIL_POINT_INCREASE => __('首次绑定邮箱送积分'),
            self::RULE_CODE_POINT_LUCKY_DRAW => __('抽奖活动积分奖品'),
            self::RULE_CODE_POINT_LUCKY_DRAW_CONSUME => __('参与抽奖活动消耗积分'),
            self::RULE_CODE_POINT_USER_PROFILE_INCREASE => __('完善用户信息送积分'),
            self::RULE_CODE_POINT_POINT_SHOP_DECREASE => __('积分商城消耗'),
            self::RULE_CODE_POINT_POINT_SHOP_RETURN => __('积分商城退回'),
            self::RULE_CODE_POINT_POINTS_ORDERING => __('下单累计积分'),
        ];
    }

    public static function getFeeList()
    {
        return [
            self::TYPE_FREIGHT_FEE => __('运费'),
            self::TYPE_INSURANCE_FEE => __('保险费'),
            self::TYPE_TARIFF_FEE => __('关税费'),
            self::TYPE_VALUE_ADDED_AMOUNT => __('增值费'),
            self::TYPE_LINE_SERVICE_FEE => __('渠道增值费'),
            self::TYPE_LINE_RULE_FEE => __('渠道规则费'),
            self::TYPE_PACKAGE_SERVICE_FEE => __('包裹增值服务费'),
        ];
    }

    public static function getValidTimeList()
    {
        return [
            '0' => __('永久'),
            '-7' => __('1周'),
            '1' => __('1个月'),
            '3' => __('3个月'),
            '6' => __('6个月'),
            '12' => __('1年'),
            '24' => __('2年'),
        ];
    }

    public function getResourceTypeNameAttribute()
    {
        return !empty($this->resource_type) ? (self::getResourceTypeList()[$this->resource_type] ?? '') : '';
    }

    public function getTypeNameAttribute()
    {
        return !empty($this->type) ? (self::getTypeList()[$this->type] ?? '') : '';
    }

    public function config($modelName)
    {
        return $this->hasOne($modelName, 'income_outlay_rule_id', 'id');
    }

}

