<?php

namespace App\Console\Commands;

use App\Models\CompanyExpressModel;
use Exception;
use Illuminate\Console\Command;
use App\Services\ExpressCompanies\ExpressCompanies;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class LogisticsCommand extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:logistics-channels {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '拉取物流渠道';

    /**
     * Execute the console command.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        $res = CompanyExpressModel::where('enable', 1)->select('code', 'id')->get();

        $res->each(function($item) {
            $expressCompanies = new ExpressCompanies($item->code);

            $expressCompanies->channels($item->id);
            $this->info('拉取物流渠道：'.$item->code);
        });
    }
}
