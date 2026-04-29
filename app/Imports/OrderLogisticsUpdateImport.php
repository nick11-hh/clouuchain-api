<?php

namespace App\Imports;

use App\Exceptions\AccidentException;
use App\Lib\Code;
use App\Models\LogisticsApplyModel;
use App\Models\Order;
use App\Models\Package;
use App\Models\ShopOrderLogs;
use App\Models\SystemConfig;
use App\Services\Base\PackageBaseService;
use App\Services\Base\SystemConfigService as SystemConfigBaseService;
use App\Services\Tracking\TrackingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Validators\Failure;

class OrderLogisticsUpdateImport implements ToModel, WithStartRow, WithMapping, SkipsOnFailure
{
    /**
     * @param array $row
     * @return void
     * @throws AccidentException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function model(array $row)
    {
        $logContext = [
            'order_id' => $row['order_id'] ?? null,
            'tracking_number' => $row['tracking_number'] ?? null,
        ];
        logger()->info('导入运单处理开始', $logContext);

        Validator::make($row, [
            'order_id' => 'required|string',
            'tracking_number' => 'required|string',
            'logistics_type' => 'sometimes|nullable|string',
            'tracking_url' => 'sometimes|nullable|string',
        ])->validate();

        $order = Order::query()->with('packages')->where('order_id', $row['order_id'])->first();
        if (empty($order)) {
            logger()->warning('导入运单处理失败：订单不存在', $logContext);
            throw new AccidentException("订单{$row['order_id']}不存在");
        }
        logger()->info('导入运单处理：订单查询成功', [
            'order_id' => $order->order_id,
            'order_pk' => $order->id,
        ]);

        if (empty($order->packages)) {
            logger()->warning('导入运单处理失败：订单未生成包裹', [
                'order_id' => $order->order_id,
                'order_pk' => $order->id,
            ]);
            throw new AccidentException("订单{$order->order_id}未付款生成包裹");
        }
        $package = $order->packages->first();
        if (empty($package)) {
            logger()->warning("订单 {$row['order_id']} 存在包裹数据但实际包裹为空");
            return;
        }
        logger()->info('导入运单处理：包裹查询成功', [
            'order_id' => $order->order_id,
            'package_id' => $package->id,
        ]);

        $logisticsApply = LogisticsApplyModel::query()->where('package_id', $package->id)->latest()->first();
        DB::transaction(function () use ($logisticsApply, $row, $package, $order) {
            if (empty($logisticsApply) || $logisticsApply->way_bill_number != $row['tracking_number']) {
                logger()->info('导入运单处理：准备创建物流申请记录', [
                    'order_id' => $order->order_id,
                    'package_id' => $package->id,
                    'old_tracking_number' => $logisticsApply->way_bill_number ?? null,
                    'new_tracking_number' => $row['tracking_number'],
                ]);
                $logisticsApply = LogisticsApplyModel::query()->create([
                    'package_id' => $package->id,
                    'way_bill_number' => $row['tracking_number'],
                    'last_mail_tracking_number' => $row['tracking_number'],
                    'remark' => '',
                    'last_mail_time' => now()
                ]);
                ShopOrderLogs::addLog([
                    'order_id'      => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_SYNC_THIRD_PARTY_SEND,
                    'content'       => '导入运单号成功，运单号为 ' . $logisticsApply->way_bill_number,
                    'operator_id'   => 0
                ]);
                logger()->info('导入运单号记录', [
                    'order_id' => $order->order_id,
                    'package_id' => $package->id,
                    'tracking_number' => $logisticsApply->way_bill_number,
                ]);

                // 通知平台订单发货
                $sync_waybill_number = SystemConfigBaseService::getConfigValue(SystemConfig::SYNC_WAYBILL_NUMBER);
                if($sync_waybill_number == 1) {
                    logger()->info('导入运单处理：触发平台发货同步', [
                        'order_id' => $order->order_id,
                        'package_id' => $package->id,
                    ]);
                    (new PackageBaseService($package))->packagePlatformDelivery();
                }

                //注册17track物流轨迹
                try {
                    (new TrackingService())->registerByLogisticsApply($logisticsApply);
                    logger()->info('导入运单处理：注册17track成功', [
                        'order_id' => $order->order_id,
                        'package_id' => $package->id,
                        'tracking_number' => $logisticsApply->way_bill_number,
                    ]);
                } catch (\Exception $e) {
                    info('注册17track物流轨迹失败', [$e]);
                }

                // 更新运单状态
                $order->logistics_status = Package::LOGISTICS_APPLY_SUCCESS;
                $order->save();
                $package->logistics_status = Package::LOGISTICS_APPLY_SUCCESS;
                $package->save();
                logger()->info('导入运单处理：状态更新成功', [
                    'order_id' => $order->order_id,
                    'package_id' => $package->id,
                    'logistics_status' => Package::LOGISTICS_APPLY_SUCCESS,
                ]);
                return;
            }

            logger()->info('导入运单处理：运单号一致，跳过更新', [
                'order_id' => $order->order_id,
                'package_id' => $package->id,
                'tracking_number' => $row['tracking_number'],
            ]);
        });
        logger()->info('导入运单处理结束', [
            'order_id' => $order->order_id,
            'tracking_number' => $row['tracking_number'],
        ]);

    }

    public function startRow(): int
    {
        return 2;
    }


    public function map($row): array
    {
        if (count($row) < 4) {
            throw new AccidentException('The Excel data missing', Code::OPERATE_FAIL);
        }
        return [
            'order_id' => trim($row[0]),
            'tracking_number' => trim($row[1]),
            'logistics_type' => trim($row[2]),
            'tracking_url' => trim($row[3]),
        ];
    }

    public function onFailure(Failure ...$failures)
    {
        // TODO: Implement onFailure() method.
    }
}
