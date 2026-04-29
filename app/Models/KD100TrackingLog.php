<?php

namespace App\Models;

use App\Models\Traits\Basis;

class KD100TrackingLog extends Model
{
    use Basis;

    protected $table = 'dsp_kd100_tracking_logs';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    public const TYPE_PACKAGE = 1;
    public const TYPE_ORDER = 2;

    //状态subscribed-订阅0-在途1-揽收2-疑难3-签收4-退签5-派件6-退回7-转投8-清关14-拒签
    public const STATUS_SUBSCRIBED = 'subscribed';
    public const STATUS_IN_TRANSIT = '0';
    public const STATUS_RECEIVE = '1';
    public const STATUS_DIFFICULT = '2';
    public const STATUS_SIGNED = '3';
    public const STATUS_BACK_SIGN = '4';
    public const STATUS_DISPATCH = '5';
    public const STATUS_BACK = '6';
    public const STATUS_SWITCH = '7';
    public const STATUS_CLEARANCE = '8';
    public const STATUS_REFUSE_SIGN = '14';

    public function getStatusNameAttribute()
    {
        return self::statusList()[$this->status] ?? '';
    }


    public static function statusList()
    {
        return [
            self::STATUS_SUBSCRIBED => __('已订阅'),
            self::STATUS_IN_TRANSIT => __('在途'),
            self::STATUS_RECEIVE => __('揽收'),
            self::STATUS_DIFFICULT => __('疑难'),
            self::STATUS_SIGNED => __('签收'),
            self::STATUS_BACK_SIGN => __('退签'),
            self::STATUS_DISPATCH => __('派件'),
            self::STATUS_BACK => __('退回'),
            self::STATUS_SWITCH => __('转投'),
            self::STATUS_CLEARANCE => __('清关'),
            self::STATUS_REFUSE_SIGN => __('拒签'),
        ];
    }
}
