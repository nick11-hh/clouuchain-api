<?php

namespace App\Console\Commands;

use App\Models\Landlord\Tenant;
use App\Models\Order;
use App\Models\ThirdPartyWarehouseConfig;
use App\Services\ThirdPartyWarehouse\ThirdPartyWarehouseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Commands\Concerns\TenantAware;

class SyncOrderThirdPartyWarehouseSendByCustomerId extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:order-warehouse-status-by-customer {customer_id} {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '根据客户编号同步未发货订单的第三方仓库发货状态';

    /**
     * Execute the console command.
     *
     * @return bool
     * @throws Exception
     */
    public function handle()
    {
        $customerId = $this->argument('customer_id');
        $time = Carbon::now()->toDateTimeString();
        info('开始根据客户编号同步马帮订单状态' . $time, ['customer_id' => $customerId, 'tenant' => Tenant::current()->toArray()]);

        $count = 0;

        // 先统计符合条件的订单总数
        $totalOrdersCount = Order::query()
            ->where('paymented_at', '>', now()->subMonths(1))
            ->where('customer_id', $customerId) // 根据客户编号筛选
            ->where('order_status', Order::STATUS_PENDING)
            ->where('is_shipping', 0)
            ->whereNotNull('fulfillment_platform')
            ->where('fulfillment_platform', '=', 'mabang')
            ->count();

        $this->info("待处理订单总数: {$totalOrdersCount}");

        if ($totalOrdersCount === 0) {
            $this->info("客户编号 {$customerId} 没有待处理的订单，跳过处理");
            info('客户编号无待处理订单', [
                'customer_id' => $customerId,
                'tenant' => Tenant::current()->toArray(),
            ]);
            return true;
        }

        try {
            $config = ThirdPartyWarehouseConfig::getConfig();
            if (!empty($config)) {
                $warehouseService = new ThirdPartyWarehouseService($config);

                Order::query()
                    ->where('paymented_at', '>', now()->subMonths(1))
                    ->where('customer_id', $customerId) // 根据客户编号筛选
                    ->where('order_status', Order::STATUS_PENDING)
                    ->where('is_shipping', 0)
                    ->whereNotNull('fulfillment_platform')
                    ->where('fulfillment_platform', '=', 'mabang')
                    ->chunkById(100, function ($orders) use ($warehouseService, &$count) {
                        $count += count($orders); // 正确统计实际订单数量
                        $orders->each(function ($order) use ($warehouseService) {
                            try {
                                $warehouseService->syncOrderSendStatus($order);
                                $this->info("成功同步订单 {$order->order_id} 的状态");
                            } catch (Exception $e) {
                                info('同步马帮状态失败_' . $order->order_id, [
                                    'tenant_id' => Tenant::current()->id,
                                    'message' => $e->getMessage(),
                                    'file' => $e->getFile(),
                                    'line' => $e->getLine(),
                                    'customer_id' => $order->customer_id
                                ]);
                                $this->error("同步订单 {$order->order_id} 状态失败: " . $e->getMessage());
                            }
                        });
                    });
            }
        } catch (Exception $e) {
            info('根据客户编号同步马帮失败', [
                'customer_id' => $customerId,
                'tenant' => Tenant::current()->toArray(),
                'message' => $e->getMessage()
            ]);
            $this->error("根据客户编号同步马帮失败: " . $e->getMessage());
        }

        $this->info("完成客户编号 {$customerId} 的订单状态同步，共处理 {$count} 个订单");
        info('根据客户编号同步马帮订单状态完成' . $time, [
            'customer_id' => $customerId,
            'tenant' => Tenant::current()->toArray(),
            'count' => $count
        ]);

        return true;
    }
}
