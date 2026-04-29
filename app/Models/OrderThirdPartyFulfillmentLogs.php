<?php

namespace App\Models;

use App\Http\Resources\Admin\ThirdPartyWarehouseConfigInfo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderThirdPartyFulfillmentLogs extends Model
{
    use HasFactory;

    protected $table = 'dsp_order_third_party_fulfillment_logs';

    protected $guarded = [];

    protected $casts = [];

    const TYPE_ORDER = 1;//订单
    const TYPE_GOODS = 2;//产品


    const FULFILLMENT_PUSH_WAITING  = 1;
    const FULFILLMENT_PUSH_SUCCESS  = 2;
    const FULFILLMENT_PUSH_ERROR  = 3;

    public const UPDATE_ORDER_SHIPPING_ADDRESS = 1;
    public const UPDATE_ORDER_REFUND_AND_SUPPLEMENT_AMOUNT = 2;

    const FULFILLMENT_PUSH_STATUS_LIST = [
        self::FULFILLMENT_PUSH_WAITING => '推送中',
        self::FULFILLMENT_PUSH_SUCCESS => '推送成功',
        self::FULFILLMENT_PUSH_ERROR => '推送失败',
    ];

    const UPDATE_TYPE_LIST = [
        self::UPDATE_ORDER_SHIPPING_ADDRESS => '更新订单收货地址',
        self::UPDATE_ORDER_REFUND_AND_SUPPLEMENT_AMOUNT => '更新订单退款和补款金额',
    ];


    public function operateUser()
    {
        return $this->belongsTo(Admin::class, 'operate_id', 'id');
    }

    public function getStatusNameAttribute()
    {
        return self::FULFILLMENT_PUSH_STATUS_LIST[$this->status] ?? '-';
    }

    public function getPlatformNameAttribute()
    {
        return ThirdPartyWarehouseConfig::WAREHOUSE_PLATFORM_LIST[$this->platform] ?? '-';
    }

    public static function getUpdateTypeName($type = 1)
    {
        return self::UPDATE_TYPE_LIST[$type] ?? '其他类型';
    }
}
