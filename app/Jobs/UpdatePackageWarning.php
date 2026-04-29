<?php

namespace App\Jobs;

use App\Events\UpdatedPackageWarning;
use App\Models\CompanyProp;
use App\Models\Package;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdatePackageWarning implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $configs = CompanyProp::query()
            ->where('type', CompanyProp::PACKAGE_WARNING)
            ->get();

        foreach ($configs as $config) {
            if ($config->prop) {
                $this->updateWarningPackage($config->company_id, (int) $config->prop);
            }
        }
    }

    protected function updateWarningPackage(int $companyId, int $days)
    {
        $packages = Package::query()
            ->where('company_id', $companyId)
            ->where('status', Package::STATUS_WAIT_STORAGE)
            ->where('created_at', '<', now()->subDays($days))
            ->where('is_warning', 0)
            ->get();

        info('发送订单预警信息', [
                'express_num' => $packages
                    ->pluck('express_num')
                    ->flatten()
                    ->values()
                    ->toArray()
            ]
        );

        $packages->each(function ($package) {
            $package->update(['is_warning' => 1]);
            event(new UpdatedPackageWarning($package));
        });
    }
}
