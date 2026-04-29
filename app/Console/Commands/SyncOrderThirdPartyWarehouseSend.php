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

class SyncOrderThirdPartyWarehouseSend extends Command
{
    use TenantAware;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:order-warehouse-status {--tenant=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步未发货订单的第三方仓库发货状态';

    /**
     * Execute the console command.
     *
     * @return bool
     * @throws Exception
     */
    public function handle()
    {
        $time = Carbon::now()->toDateTimeString();
        $tenantId = Tenant::current()->id;
        info('开始同步马帮订单状态 ' . $time, ['tenant_id' => $tenantId]);
        $count = 0;
        try {
            $config = ThirdPartyWarehouseConfig::getConfig();
            if (!empty($config)) {
                $warehouseService = new ThirdPartyWarehouseService($config);
                Order::query()
                    ->with(['packages']) // 预加载关联，避免 N+1 查询
                    ->where('paymented_at', '>', now()->subMonths(2))
                    ->where(function ($query) {
                        $query->where('order_status', Order::STATUS_PENDING)
                              ->orWhere('outbound_time', '>', Carbon::now()->subDays(5));
                    })
                    ->where('fulfillment_platform', 'mabang')
                    ->chunkById(10, function ($orders) use ($warehouseService, &$count, $tenantId) {
                        $count += $orders->count();
                        try {
                            // 批量同步（每批最多10个订单，只需1次API请求）
                            $warehouseService->batchSyncOrderSendStatus($orders);
                        } catch (Exception $e) {
                            info('批量同步马帮状态失败', [
                                'tenant_id' => $tenantId,
                                'order_ids' => $orders->pluck('order_id')->toArray(),
                                'error' => $e->getMessage()
                            ]);
                        }
                    });
            }
        } catch (Exception $e) {
            info('同步马帮失败', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
        }
        info('同步马帮订单状态完成 ' . $time, ['tenant_id' => $tenantId, 'count' => $count]);
        return true;
    }
}
