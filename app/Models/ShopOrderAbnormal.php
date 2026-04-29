<?php

namespace App\Models;

use App\Models\Traits\Basis;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopOrderAbnormal extends Model
{
    use HasFactory;

    use Basis, SoftDeletes;

    protected $table = 'dsp_shop_order_abnormal';

    const ABNORMAL_GOODS_ADD = 'goods_add';
    const ABNORMAL_GOODS_REMOVE = 'goods_remove';
    const ABNORMAL_GOODS_UPDATE = 'goods_update';
    const ABNORMAL_ORDER_CANCEL = 'order_cancel';
    const ABNORMAL_ADDRESS_UPDATE = 'address_update';
    const ABNORMAL_APPLY_LOGISTICS_FAILURE = 'apply_logistics_failure';
    const ABNORMAL_DELIVERY_FAILURE = 'delivery_failure';
    const ABNORMAL_OTHER_FULFILMENT = 'other_fulfillment';


    const PLATFORM_ABNORMAL_LIST = [
        self::ABNORMAL_GOODS_ADD => '增加商品',
        self::ABNORMAL_GOODS_REMOVE => '减少商品',
//        self::ABNORMAL_GOODS_UPDATE => '商品变更',
        self::ABNORMAL_ORDER_CANCEL => '订单取消',
        self::ABNORMAL_ADDRESS_UPDATE => '地址更新',
//        self::ABNORMAL_APPLY_LOGISTICS_FAILURE => '运单申请失败',
        self::ABNORMAL_DELIVERY_FAILURE => '交运失败',
        self::ABNORMAL_OTHER_FULFILMENT => '其他供应商履约',
    ];

    const STATUS_WAIT_DEAL = 1;  // 待处理
    CONST STATUS_PROCESSED = 2;  // 已处理

    const DEAL_TYPE_REAPPLY_LOGISTICS = 'reapply_logistics';
    const DEAL_TYPE_ORDER_CANCEL = 'order_cancel';
    const DEAL_TYPE_MOVE_TO_QUOTING = 'move_to_quoting';
    const DEAL_TYPE_IGNORE_ABNORMAL = 'ignore_abnormal';
    const DEAL_TYPE_DELIVERY_SUCCESS = 'delivery_success';

    public function getAbnormalReasonNameAttribute()
    {
        return self::PLATFORM_ABNORMAL_LIST[$this->platform_abnormal_reason] ?? '-';
    }

}
