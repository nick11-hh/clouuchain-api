<?php

namespace App\Console\Commands;

use App\Jobs\QueryTrackingJob;
use App\Models\Landlord\Tenant;
use App\Models\LogisticsApplyModel;
use App\Models\Package;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class QueryTrackingCommand extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:query-tracking-command {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步更新包裹物流轨迹';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        info('开始同步包裹物流', [Tenant::current()->toArray()]);
        Package::query()->with('logisticsApply')->whereIn('status', [Package::STATUS_OUTBOUND])->whereHas('logisticsApply', function ($query) {
            return $query->where('tracking_platform', LogisticsApplyModel::TRACKING_PLATFORM_YAOQI);
        })->chunkById(100, function ($packages) {
            dispatch_sync(new QueryTrackingJob($packages->pluck('id')->toArray()));
        });
        return true;
    }
}
