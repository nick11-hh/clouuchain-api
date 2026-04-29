<?php

namespace App\Console\Commands\Test;

use App\Models\Order;
use App\Services\ExpressCompanies\UBI\UBIService;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class TestUBI extends Command
{
    use TenantAware;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:ubi-order-create {orderId=3} {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试UBI申请运单号';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $order = Order::with(['lineItems', 'shippingAddress', 'channel:id,code,name'])->find(intval($this->argument('orderId')));
        return (new UBIService)->place($order);
    }
}
