<?php

namespace App\Console\Commands\DataFixer;

use App\Jobs\CreateShopifyWebhooks;
use App\Lib\Platform;
use App\Models\Custom;
use App\Models\CustomInvoiceAddressModel;
use App\Models\Order;
use App\Models\ShopModel;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class OrderQuotePriceFix extends Command
{
    use TenantAware;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:quote-price-fix {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '修复报价数据';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $orders = Order::query()->where('sku_logistics_fee', '>', 0)->where('order_status', '>', Order::STATUS_PENDING)->get();
        foreach ($orders as $order) {
            dump($order->order_id);
            $order->vendor_price = bcadd($order->vendor_price, $order->sku_logistics_fee);
            $order->sku_logistics_fee = 0;
            $order->save();
        }
    }
}
