<?php

namespace App\Console\Commands;

use App\Jobs\AutoPayOrderJob;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class AutoOrderPayment extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:auto-pay-order {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '订单自动支付';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        dispatch(new AutoPayOrderJob());
    }
}
