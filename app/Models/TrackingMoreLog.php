<?php

namespace App\Models;

use App\Models\Traits\Basis;

class TrackingMoreLog extends Model
{
    use Basis;

    protected $table = 'dsp_51_tracking_logs';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    public const TYPE_PACKAGE = 1;
    public const TYPE_ORDER = 2;

    //状态subscribed-订阅pending-查询中transit-运输途中notfound-查询不到pickup-到达待取delivered-成功签收undelivered-投递失败exception-可能异常InfoReceived-待上网
    public const STATUS_SUBSCRIBED = 'subscribed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_TRANSIT = 'transit';
    public const STATUS_NOTFOUND = 'notfound';
    public const STATUS_PICKUP = 'pickup';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_UNDELIVERED = 'undelivered';
    public const STATUS_EXCEPTION = 'exception';
    public const STATUS_INFORECEIVED = 'InfoReceived';

    public function getStatusNameAttribute()
    {
        return self::statusList()[$this->status] ?? '';
    }


    public static function statusList()
    {
        return [
            self::STATUS_SUBSCRIBED => __('已订阅'),
            self::STATUS_PENDING => __('查询中'),
            self::STATUS_TRANSIT => __('运输途中'),
            self::STATUS_NOTFOUND => __('查询不到'),
            self::STATUS_PICKUP => __('到达待取'),
            self::STATUS_DELIVERED => __('成功签收'),
            self::STATUS_EXPIRED => __('运输过久'),
            self::STATUS_UNDELIVERED => __('投递失败'),
            self::STATUS_EXCEPTION => __('可能异常'),
            self::STATUS_INFORECEIVED => __('待上网'),
        ];
    }
}
