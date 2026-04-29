<?php

namespace App\Jobs;

use App\Models\ExpressLineModel;
use App\Models\ExpressOrderModel;
use App\Models\LogisticsApplyModel;
use App\Models\LogisticsChannelModel;
use App\Models\Package;
use App\Services\Base\OrderBaseService;
use App\Services\ExpressCompanies\ExpressCompanies;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class LogisticsPlaceJobV2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $packageId;

    public array $params;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($packageId, $params = [])
    {
        $this->packageId = $packageId;

        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return false
     */
    public function handle()
    {
        $package = Package::query()->with('orders')->findOrFail($this->packageId);
        if (empty($package->orders[0])) return false;
        logger('--------------申请运单号---------------', [
            'packageId' => $this->packageId,
            'params' => $this->params,
        ]);

        $changeType = $this->params['change_type'] ?? ExpressOrderModel::CHANGE_TYPE_FIRST;
        DB::transaction(function () use ($package, $changeType) {
            if (empty($package->express_companies_code)) {
                $expressLineId = $package->orders->first()->value('express_line_id');

                $expressLine = ExpressLineModel::query()->findOrFail($expressLineId);
                $logisticsChannel = LogisticsChannelModel::with('expressCompanies:id,code')
                                                         ->where('code', $expressLine->channel_code)
                                                         ->orWhere('name', $expressLine->channel_code)
                                                         ->first();
                if (!empty($logisticsChannel)) {
                    $package->express_companies_id = $logisticsChannel->expressCompanies->id ?? 0;
                    $package->express_companies_code = $logisticsChannel->expressCompanies->code ?? '';
                    $package->express_channel_code = $logisticsChannel->code ?? '';
                }
            }


            $expressCompanies = new ExpressCompanies($package->express_companies_code);
            try {
                if (empty($package->packageAddress->tax)) {
                    $tax = $expressCompanies->getShopTax($package->orders[0]);
                    if ($tax) {
                        foreach ($package->orders as $order) {
                            $order->shippingAddress->tax = $tax;
                            $order->shippingAddress->save();
                        }
                        //更新收件人税号
                        $package->packageAddress->update(['tax' => $tax]);
                    }
                }

                $logisticsApply = $this->createOrUpdateApply($package, [], $changeType !== ExpressOrderModel::CHANGE_TYPE_FIRST);
                if (empty($logisticsApply->way_bill_number)) {
                    $expressCompanies->place($package, $logisticsApply);
                } else {
                    $logisticsApply->remark = '';
                    $logisticsApply->save();
                    $package->logistics_status = Package::LOGISTICS_APPLY_SUCCESS;
                    $package->save();
                    $package->orders->each(function ($order) {
                        (new OrderBaseService($order))->syncPackageLogisticApplyStatus();
                    });
                }

            } catch (\Throwable $e) {
                $logisticsApplyData = [
                    'remark'     => $e->getMessage(),
                ];

                $this->createOrUpdateApply($package, $logisticsApplyData);

                $package->logistics_status = Package::LOGISTICS_APPLY_FAILURE;
                $package->save();

                $package->orders->each(function ($order) {
                    (new OrderBaseService($order))->syncPackageLogisticApplyStatus();
                });



                info('申请运单失败', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            }
        });
    }

    public function createOrUpdateApply($package, $params = [], $isChange = false)
    {
        $logisticsApply = LogisticsApplyModel::query()->where('package_id', $package->id)->latest('id')->first();
        if (empty($logisticsApply) || $isChange) {
            $logisticsApply = LogisticsApplyModel::query()->create([
                'package_id' => $package->id,
                'package_sn' => LogisticsApplyModel::getPackageSn(),
                'remark' => ''
            ]);
        } else {
            $logisticsApply->update(['remark' => $params['remark'] ?? '']);
        }
        return $logisticsApply;
    }

}
