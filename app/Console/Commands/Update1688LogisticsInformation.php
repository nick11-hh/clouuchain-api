<?php

namespace App\Console\Commands;

use App\Models\PurchaseOrdersModel;
use App\Services\Admin\PurchaseOrderService;
use Illuminate\Console\Command;

class Update1688LogisticsInformation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dsp:update-1688-logistics-information';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新1688物流信息';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // 获取符合条件的id
        $ids = PurchaseOrdersModel::query()->whereHas('supplier', function ($query) {
            $query->where('type', 1);
        })->where('platform_sn', '!=', '')->where('shipment_number', '')->pluck('id')->toArray();

        $model = new PurchaseOrdersModel();
        $purchaseOrderService = new PurchaseOrderService($model);
        try {
            return $purchaseOrderService->syncPurchaseOrdersStatus($ids);
        } catch (\Exception $e) {
            $this->info($e->getMessage());
            return false;
        }
    }
}
