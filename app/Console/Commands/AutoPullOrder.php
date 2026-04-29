<?php

namespace App\Console\Commands;

use App\Jobs\AutoPullOrderJob;
use App\Models\ShopModel;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class AutoPullOrder extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:auto-pull-order {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动拉取平台订单';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shops = ShopModel::where(['status' => ShopModel::STATUS_AUTH, 'enable' => ShopModel::ENABLE])->get();
        foreach ($shops as $shop) {
            dispatch(new AutoPullOrderJob($shop));
        }

    }
}
