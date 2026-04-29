<?php

namespace App\Console\Commands\DataFixer;

use App\Models\LogisticsApplyModel;
use App\Models\Order;
use App\Models\Package;
use App\Services\Admin\PackageService;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class GenerateOrderPackage extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:order-package {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成订单历史数据包裹';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        Order::query()->whereIn('order_status', [Order::STATUS_PENDING, Order::STATUS_APPLY_NUM, Order::STATUS_SHIPPED])->whereDoesntHave('packages')->chunk(1000, function ($orders) {
            foreach ($orders as $order) {
                try {
                    if (!$order->packages->isEmpty()) continue;
                    dump($order->order_id);
                    $service = new PackageService();
                    $package = $service->createByOrder($order);
                    $statusMap = [
                        Order::STATUS_PENDING => Package::STATUS_WAIT_DEAL,
                        Order::STATUS_APPLY_NUM => Package::STATUS_DISTRIBUTION,
                        Order::STATUS_SHIPPED => Package::STATUS_OUTBOUND,
                    ];
                    $package->logistics_status = $order->logistics_status;
                    $package->stock_status = $order->stock_status;
                    $package->status = $statusMap[$order->order_status] ?? Package::STATUS_CANCELED;
                    $package->is_shipping = $order->is_shipping;
                    $package->save();
                    LogisticsApplyModel::query()->where('order_id', $order->order_id)->update([
                        'package_id' => $package->id,
                    ]);
//                    foreach ($order->expressOrders as $expressOrder) {
//                        LogisticsApplyModel::query()->where('package_sn', $expressOrder->package_sn)->update([
//                            'package_id' => $package->id,
//                            'order_id' => $order->id
//                        ]);
//                    }
                } catch (\Exception $exception) {
                    dump($exception);
                }
            }
        });
    }
}
