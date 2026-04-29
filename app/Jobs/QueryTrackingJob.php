<?php

namespace App\Jobs;

use App\Lib\Code;
use App\Models\ExpressOrderModel;
use App\Models\ExpressOrderTrackingModel;
use App\Models\LogisticsApplyModel;
use App\Models\Order;
use App\Models\Package;
use App\Services\Admin\ExpressOrderService;
use App\Services\ExpressCompanies\ExpressCompanies;
use App\Services\Tracking\TrackingService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class QueryTrackingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $packageIds;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($packageIds)
    {
        $this->packageIds = $packageIds;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        logger('--------------同步物流轨迹队列开始执行---------------');
        logger('params：', $this->packageIds);
        //物流轨迹配置
        if (empty(TrackingService::enableTracking())) {
            logger('物流轨迹配置未配置');
            return true;
        }

        //获取需要查询轨迹的数据
        $packages = Package::query()->with('logisticsApply')->whereIn('id', $this->packageIds)->whereHas('logisticsApply', function ($query) {
            return $query->where('tracking_platform', LogisticsApplyModel::TRACKING_PLATFORM_YAOQI);
        })->get();
        $trackingService = new TrackingService();
        $packages->each(function ($packages) use ($trackingService) {
            $trackingService->syncPackageTracking($packages);
        });
    }

}
