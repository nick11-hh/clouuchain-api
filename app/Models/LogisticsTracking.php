<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogisticsTracking extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_logistics_tracking';

    protected $guarded = [];

    CONST STATUS_NOT_FOUND = 'NotFound';
    CONST STATUS_INFO_RECEIVED = 'InfoReceived';
    CONST STATUS_IN_TRANSIT = 'InTransit';
    CONST STATUS_EXPIRED = 'Expired';
    CONST STATUS_AVAILABLE_FOR_PICKUP = 'AvailableForPickup';
    CONST STATUS_OUT_FOR_DELIVERY = 'OutForDelivery';
    CONST STATUS_DELIVERY_FAILURE = 'DeliveryFailure';
    CONST STATUS_DELIVERED = 'Delivered';
    CONST STATUS_EXCEPTION = 'Exception';

    CONST LOGISTICS_STATUS_LIST = [
        self::STATUS_NOT_FOUND => '无轨迹',
        self::STATUS_INFO_RECEIVED => '待揽收',
        self::STATUS_IN_TRANSIT => '运输中',
        self::STATUS_EXPIRED => '运输超时',
        self::STATUS_AVAILABLE_FOR_PICKUP => '待取件',
        self::STATUS_OUT_FOR_DELIVERY => '派送中',
        self::STATUS_DELIVERY_FAILURE => '派送失败',
        self::STATUS_DELIVERED => '已签收',
        self::STATUS_EXCEPTION => '物流异常',
    ];



}
