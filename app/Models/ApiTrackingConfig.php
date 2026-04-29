<?php

namespace App\Models;

use App\Models\Traits\Basis;

class ApiTrackingConfig extends Model
{
    use Basis;

    protected $table = 'dsp_api_tracking_config';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    //类型1-快递100;2-51tracking;3-17track
    public const TYPE_KUAIDI_100 = 1;
    public const TYPE_51_TRACKING = 2;
    public const TYPE_17_TRACK = 3;


    /**
     * @param bool $international
     * @return int
     */
    public static function serviceStatus(bool $international = false): int
    {
        $config = self::query()->first();

        if (!$config) {
            return 0;
        }

        if ($international) {
            return !empty($config['51tracking_app_key']) || !empty($config['17track_app_key']);
        }

        return !empty($config['kd100_customer_id']) && !empty($config['kd100_key']);
    }

    public static function callBackUrl($companyId, $type)
    {
        $company = Company::query()->findOrFail($companyId);

        if ($type == self::TYPE_KUAIDI_100) {
            return config('app.url') . "/kd100/{$company->uuid}/subscribe-callback";
        }

        if ($type == self::TYPE_51_TRACKING) {
            return config('app.url') . "/51tracking/{$company->uuid}/subscribe-callback";
        }

        return '';
    }


    public static function typeList()
    {
        return [
            self::TYPE_KUAIDI_100 => __('快递100'),
            self::TYPE_51_TRACKING => __('51Tracking'),
            self::TYPE_17_TRACK => __('17track'),
        ];
    }

    public static function globalSubscribe()
    {
        $trackingConfig = ApiTrackingConfig::query()->first();
        return $trackingConfig->global_subscribe ?? null;
    }
}
