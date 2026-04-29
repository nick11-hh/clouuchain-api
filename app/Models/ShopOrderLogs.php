<?php

namespace App\Models;

use App\Models\Traits\Basis;

class ShopOrderLogs extends Model
{
    use Basis;

    protected $table = 'dsp_shop_order_logs';

    // 日志类型 1-报价 2-改价 3-修改收件信息
    public const OPERATOR_TYPE_VENDOR_PRICE = 1;
    public const OPERATOR_TYPE_VENDOR_CHANGE_PRICE = 2;
    public const OPERATOR_TYPE_ADDRESS = 3;
    public const OPERATOR_TYPE_PUSH_THIRD_PARTY_SUCCESS = 4;
    public const OPERATOR_TYPE_PUSH_THIRD_PARTY_ERROR = 5;
    public const OPERATOR_TYPE_SYNC_THIRD_PARTY_TRACK_NUMBER = 6;
    public const OPERATOR_TYPE_SYNC_THIRD_PARTY_SEND = 7;
    public const OPERATOR_TYPE_PULL_ORDER = 8;
    public const OPERATOR_TYPE_CHANGE_LOGISTICS = 9;
    public const OPERATOR_TYPE_ORDER_REFUND = 10;
    public const OPERATOR_TYPE_SUPPLEMENT_FEE = 11;
    public const OPERATOR_TYPE_DELETE_ITEM = 12;
    public const OPERATOR_TYPE_RESTORE_ITEM = 13;
    public const OPERATOR_TYPE_CANCEL = 14;
    public const OPERATOR_TYPE_CHANGE_QUOTE_PRICE = 15;
    public const OPERATOR_TYPE_MARK_IN_DISTRIBUTION = 16;
    public const OPERATOR_TYPE_ROLLBACK_QUOTE_ASK = 17;
    public const OPERATOR_TYPE_RESTORE_ORDER = 18;
    public const OPERATOR_TYPE_MANUALLY_ADD_ORDER_GOODS = 19;
    public const OPERATOR_TYPE_MANUALLY_UPDATE_ORDER_GOODS = 60;
    public const OPERATOR_TYPE_ORDER_ARCHIVE = 20;
    public const OPERATOR_TYPE_ROLLBACK_ARCHIVE = 21;
    public const OPERATOR_TYPE_ORDER_MERGE = 22;
    public const OPERATOR_TYPE_ORDER_SPLIT = 23;
    public const OPERATOR_TYPE_MOVE_TO_DISTRIBUTION = 24;
    public const OPERATOR_TYPE_PLATFORM_DELIVER = 25;
    public const OPERATOR_TYPE_PAYMENT = 26;
    public const OPERATOR_TYPE_DIANXIAOMI_SHIPPED = 30;
    public const OPERATOR_TYPE_DIANXIAOMI_PULL_ORDER = 31;
    public const OPERATOR_TYPE_PAYMENTED = 50;
    public const OPERATOR_TYPE_SET_SHELVE = 51;
    public const OPERATOR_TYPE_CANCEL_SHELVE = 52;
    public const OPERATOR_TYPE_CREATE_ORDER = 53;
    public const OPERATOR_TYPE_DELETE_ORDER = 99;

    public const OPERATOR_TYPE_LIST = [
        self::OPERATOR_TYPE_VENDOR_PRICE => '订单报价',
        self::OPERATOR_TYPE_VENDOR_CHANGE_PRICE => '订单改价',
        self::OPERATOR_TYPE_ADDRESS => '修改收件信息',
        self::OPERATOR_TYPE_PUSH_THIRD_PARTY_SUCCESS => '推送第三方成功',
        self::OPERATOR_TYPE_PUSH_THIRD_PARTY_ERROR => '推送第三方失败',
        self::OPERATOR_TYPE_SYNC_THIRD_PARTY_TRACK_NUMBER => '第三方已申请物流',
        self::OPERATOR_TYPE_SYNC_THIRD_PARTY_SEND => '第三方已发货',
        self::OPERATOR_TYPE_PULL_ORDER => '拉取订单',
        self::OPERATOR_TYPE_CHANGE_LOGISTICS => '更换运单',
        self::OPERATOR_TYPE_ORDER_REFUND => '订单退款',
        self::OPERATOR_TYPE_SUPPLEMENT_FEE => '补收费用',
        self::OPERATOR_TYPE_DELETE_ITEM => '删除订单商品',
        self::OPERATOR_TYPE_RESTORE_ITEM => '恢复订单商品',
        self::OPERATOR_TYPE_CANCEL => '取消订单',
        self::OPERATOR_TYPE_CHANGE_QUOTE_PRICE => '调整报价',
        self::OPERATOR_TYPE_MARK_IN_DISTRIBUTION => '标记配货中', //仅针对马帮ERP系统
        self::OPERATOR_TYPE_ROLLBACK_QUOTE_ASK => '打回报价中',
        self::OPERATOR_TYPE_RESTORE_ORDER => '恢复订单',
        self::OPERATOR_TYPE_MANUALLY_ADD_ORDER_GOODS => '手动增加订单商品',
        self::OPERATOR_TYPE_MANUALLY_UPDATE_ORDER_GOODS => '手动更新订单商品数量',
        self::OPERATOR_TYPE_ORDER_ARCHIVE => '订单归档',
        self::OPERATOR_TYPE_ROLLBACK_ARCHIVE => '订单取消归档',
        self::OPERATOR_TYPE_PAYMENTED => '订单已付款',
        self::OPERATOR_TYPE_DELETE_ORDER => '删除订单',
        self::OPERATOR_TYPE_ORDER_MERGE => '订单合包',
        self::OPERATOR_TYPE_ORDER_SPLIT => '订单拆包',
        self::OPERATOR_TYPE_MOVE_TO_DISTRIBUTION => '移入配货中',
        self::OPERATOR_TYPE_PLATFORM_DELIVER => '交运成功',
        self::OPERATOR_TYPE_PAYMENT => '订单支付',
        self::OPERATOR_TYPE_DIANXIAOMI_SHIPPED => '店小秘设置发货',
        self::OPERATOR_TYPE_DIANXIAOMI_PULL_ORDER => '店小秘拉单',
        self::OPERATOR_TYPE_SET_SHELVE => '订单搁置',
        self::OPERATOR_TYPE_CANCEL_SHELVE => '取消搁置',
        self::OPERATOR_TYPE_CREATE_ORDER => '创建订单',
    ];

    public function admin()
    {
        return $this->hasOne(Admin::class, 'id', 'operator_id');
    }

    public static function init($params)
    {
        return [
            'order_id'      => $params['order_id'],
            'operator_type' => $params['operator_type'] ?? 1,
            'content'       => $params['content'] ?? '',
            'operator_id'   => $params['operator_id'] ?? (auth('admin')->id() ?: 0),
            'created_at'    => now(),
            'updated_at'    => now(),
        ];
    }

    public static function addLog($params)
    {
        return self::query()->create(self::init($params));
    }

}
