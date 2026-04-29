<?php
namespace App\Services\Tracking;

use App\Models\ApiTrackingConfig;
use App\Models\LogisticsApplyModel;
use App\Models\LogisticsTracking;
use App\Models\Package;
use App\Services\Base\PackageBaseService;
use App\Services\Tracking\Track17\Track17Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TrackingService
{

    /**
     * 查询17Track是否配置并启用
     */
    public static function enableTracking(): bool
    {
        $config = ApiTrackingConfig::query()->where('status', 1)->value('17track_app_key');

        return (bool)$config;
    }


    /** 注册申请物流的轨迹
     * @param $logisticsApply
     * @return bool
     */
    public function registerByLogisticsApply($logisticsApply)
    {
        if (!self::enableTracking()) return false;
        $res = $this->register($logisticsApply->way_bill_number, $logisticsApply->logistics_carrier ?: '');
        if ($res) {
            $logisticsApply->tracking_platform = LogisticsApplyModel::TRACKING_PLATFORM_YAOQI;
            $logisticsApply->save();
        }
        return $res;
    }

    /** 同步更新物流轨迹
     * @param $package
     * @return false
     */
    public function syncPackageTracking($package)
    {
        if (!self::enableTracking()) return false;
        $logisticsApply = $package->logisticsApply;
        $trackingResult = $this->query($logisticsApply->way_bill_number);
        info('物流查询结果', [$trackingResult]);
        $trackingInfo = $trackingResult['track_info'] ?? '';
        if (empty($trackingInfo)) return false;
        // 更新数据
        DB::transaction(function () use ($trackingInfo, $package, $logisticsApply) {
            // 更新物流轨迹
            $provider = $trackingInfo['tracking']['providers'][0];
            foreach (array_reverse($provider['events']) as $track) {
                $time = Carbon::parse($track['time_iso']);
                $status = $track['stage'] ?: explode('_', $track['sub_status'])[0];
                LogisticsTracking::query()->firstOrCreate([
                    'logistics_id' => $logisticsApply->id,
                    'tracking_status' => $status,
                    'tracking_sub_status' => $track['sub_status'],
                    'event_time' => $time->toDateTimeString(),
                ], [
                    'event_zone' => $time->getTimezone(),
                    'description' => $track['description'],
                    'location' => $track['location']
                ]);
            }

            //更新物流状态
            $logisticsApply->tracking_status = $trackingInfo['latest_status']['status'];
            $logisticsApply->save();
            $package->tracking_status = $trackingInfo['latest_status']['status'];
            $package->save();

            //同步更新包裹和订单状态
            (new PackageBaseService($package))->logisticsStatusUpdate();
        });
    }

    /**
     * 注册快递单号
     * @param string $number
     * @param string $code
     * @return bool
     */
    public function register(string $number, string $code = '',)
    {
        return (new Track17Service())->register($number, $code);
    }

    /**
     * 查询轨迹
     * @param string $number
     * @param string $code
     * @param bool $isReturnTrackList
     * @return array|null
     */
    public function query(string $number, string $code = '', bool $isReturnTrackList = false)
    {
        return (new Track17Service())->query($number, $code, $isReturnTrackList);
    }

}
