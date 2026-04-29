<?php

namespace App\Console\Commands\DataFixer;

use App\Models\Order;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class OrderStatusMigration extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migration:order_status {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '订单状态改版迁移订单状态';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Order::query()->where('order_status', '>', Order::STATUS_PENDING)->chunk(100, function ($orders) {
            $mapping = [
                Order::STATUS_DELIVERY_SUCCESS => Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERY_FAILURE => Order::STATUS_SHIPPED,
                Order::STATUS_APPLY_NUM_SUCCESS => Order::STATUS_PENDING,
                Order::STATUS_APPLY_NUM_FAILURE => Order::STATUS_PENDING,
                Order::STATUS_WAIT_PRINT => Order::STATUS_APPLY_NUM,
                Order::USER_CHECKED => Order::STATUS_SHIPPED,
                Order::STATUS_WAIT_PRINT_IN_STOCK => Order::STATUS_APPLY_NUM,
                Order::STATUS_WAIT_PRINT_OUT_STOCK => Order::STATUS_APPLY_NUM,
                Order::STATUS_STOCK_PENDING => Order::STATUS_APPLY_NUM,
            ];
            $successStatus = [
                Order::STATUS_SHIPPED,
                Order::STATUS_DELIVERY_SUCCESS,
                Order::USER_CHECKED,
            ];
            foreach ($orders as $order) {
                if ($order->order_status == Order::STATUS_APPLY_NUM_SUCCESS ||  $order->order_status == Order::STATUS_WAIT_PRINT_OUT_STOCK || $order->order_status == Order::STATUS_WAIT_PRINT ||
                    $order->order_status == Order::STATUS_WAIT_PRINT_IN_STOCK || in_array($order->order_status, $successStatus)) {
                    $order->logistics_status = Order::LOGISTICS_APPLY_SUCCESS;
                }
                if ($order->order_status == Order::STATUS_APPLY_NUM_FAILURE) {
                    $order->logistics_status = Order::LOGISTICS_APPLY_FAILURE;
                }
                if ($order->order_status == Order::STATUS_WAIT_PRINT_IN_STOCK || in_array($order->order_status, $successStatus)) {
                    $order->stock_status = Order::STOCK_SUCCESS;
                }
                if ($order->order_status == Order::STATUS_WAIT_PRINT_OUT_STOCK) {
                    $order->stock_status = Order::STOCK_LACK;
                }

                if (isset($mapping[$order->order_status])) {
                    $order->order_status = $mapping[$order->order_status];
                }
                $order->save();
            }
        });
    }
}
