<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class ExpressOrderTrackingModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_express_order_tracking';

    protected $guarded = [];

    public const STATUS_NOT_QUERY = 0; //未查询
    public const STATUS_NOT_FOUND = 1; //查询不到
    public const STATUS_PARCEL_COLLECTION = 2; //包裹揽收
    public const STATUS_IN_TRANSIT = 3; //运输途中
    public const STATUS_EXPIRED = 4; //运输过久
    public const STATUS_AVAILABLE_FOR_PICKUP = 5; //到达待取
    public const STATUS_OUT_FOR_DELIVERY = 6; //派送途中
    public const STATUS_DELIVERY_FAILURE = 7; //投递失败
    public const STATUS_DELIVERED = 8; //成功签收
    public const STATUS_EXCEPTION = 9; //可能异常

    public static function statusList(): array
    {
        return [
            self::STATUS_NOT_QUERY            => __('未查询'),
            self::STATUS_NOT_FOUND            => __('查询不到'),
            self::STATUS_PARCEL_COLLECTION    => __('包裹揽收'),
            self::STATUS_IN_TRANSIT           => __('运输途中'),
            self::STATUS_EXPIRED              => __('运输过久'),
            self::STATUS_AVAILABLE_FOR_PICKUP => __('到达待取'),
            self::STATUS_OUT_FOR_DELIVERY     => __('派送途中'),
            self::STATUS_DELIVERY_FAILURE     => __('投递失败'),
            self::STATUS_DELIVERED            => __('成功签收'),
            self::STATUS_EXCEPTION            => __('可能异常'),
        ];
    }

    public function getStatusNameAttribute()
    {
        $status = self::statusList();

        return $status[$this->status];
    }

    /**
     * 17track 物流主状态
     */
    public static function track17StatusMap(): array
    {
        return [
            'NotFound'           => self::STATUS_NOT_FOUND, //查询不到，进行查询操作但没有得到结果，原因请参看子状态。
            'InfoReceived'       => self::STATUS_PARCEL_COLLECTION, //收到信息，运输商收到下单信息，等待上门取件。
            'InTransit'          => self::STATUS_IN_TRANSIT, //运输途中，包裹正在运输途中，具体情况请参看子状态。
            'Expired'            => self::STATUS_EXPIRED, //运输过久，包裹已经运输了很长时间而仍未投递成功。
            'AvailableForPickup' => self::STATUS_AVAILABLE_FOR_PICKUP, //到达待取，包裹已经到达目的地的投递点，需要收件人自取。
            'OutForDelivery'     => self::STATUS_OUT_FOR_DELIVERY, //派送途中，包裹正在投递过程中。
            'DeliveryFailure'    => self::STATUS_DELIVERY_FAILURE, //投递失败，包裹尝试派送但未能成功交付，原因请参看子状态。原因可能是：派送时收件人不在家、投递延误重新安排派送、收件人要求延迟派送、地址不详无法派送、因偏远地区不提供派送服务等。
            'Delivered'          => self::STATUS_DELIVERED, //成功签收，包裹已妥投。
            'Exception'          => self::STATUS_EXCEPTION, //可能异常，包裹可能被退回，原因请参看子状态。原因可能是：收件人地址错误或不详、收件人拒收、包裹无人认领超过保留期等。包裹可能被海关扣留，常见扣关原因是：包含敏感违禁、限制进出口的物品、未交税款等。包裹可能在运输途中遭受损坏、丢失、延误投递等特殊情况。
        ];
    }

    /**
     * 需要自动查询轨迹的状态
     */
    public static function autoQueryStatus(): array
    {
        return [
            self::STATUS_NOT_QUERY, //未查询
            self::STATUS_NOT_FOUND, //查询不到
            self::STATUS_PARCEL_COLLECTION, //包裹揽收
            self::STATUS_IN_TRANSIT, //运输途中
            self::STATUS_EXPIRED, //运输过久
            self::STATUS_AVAILABLE_FOR_PICKUP, //到达待取
            self::STATUS_OUT_FOR_DELIVERY  //派送途中
        ];
    }

    public static function init($params): array
    {
        return [
            'express_order_id'    => $params['express_order_id'] ?? 0, //物流订单ID
            'platform'            => $params['platform'] ?? '17Track', //轨迹平台 17track
            'tracking_number'     => $params['tracking_number'] ?? '', //物流跟踪号
            'carrier_code'        => $params['carrier_code'] ?? '', //运输商代码
            'status'              => $params['status'] ?? 0, //物流状态: 0-未查询 1-查询不到 2-等待揽件
            'tracking_status'     => $params['tracking_status'] ?? '', //原始物流状态
            'tracking_sub_status' => $params['tracking_sub_status'] ?? '', //原始物流子状态
            'remark'              => $params['remark'] ?? '', //物流轨迹备注
        ];
    }

}
