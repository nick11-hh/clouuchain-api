<?php

namespace App\Imports;

use App\Exceptions\ErrorDataException;
use App\Jobs\FulfillmentOrderJob;
use App\Lib\Code;
use App\Models\ExpressOrderModel;
use App\Models\LogisticsApplyModel;
use App\Models\LogisticsChannelModel;
use App\Models\Order;
use App\Models\ShopOrderLogs;
use App\Models\SystemConfig;
use App\Models\ThirdPartyWarehouseConfig;
use App\Services\Admin\ExpressOrderService;
use App\Services\Admin\OrderService;
use App\Services\Base\OrderBaseService;
use App\Services\Base\PackageBaseService;
use App\Services\Base\SystemConfigService as SystemConfigBaseService;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMappedCells;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStartRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use App\Exceptions\AccidentException;

class OrderDianxiaomiImport implements ToCollection, WithStartRow, WithMapping
{

    protected array $error = [];

    protected string $importType;

    public function __construct($importType)
    {
        $this->importType = $importType;
    }

    public function collection(Collection $rows)
    {
        $this->error = $this->checkData($rows);
        if (!empty($this->error['order_ids']) || !empty($this->error['status_list']) || !empty($this->error['no_paid_list'])) {
            throw new ErrorDataException($this->error);
        }
        $orderService = new OrderService(new Order());
        foreach ($rows as $row) {
            DB::beginTransaction();
            try {
                //过滤未支付跟已搁置的订单
                $order = Order::query()
                              ->where('order_id', $row['order_id'])
                              ->where('fulfillment_platform', ThirdPartyWarehouseConfig::PLATFORM_DIANXIAOMI)
                              ->whereNotIn('order_status', [Order::STATUS_QUOTE_NO, Order::STATUS_QUOTE_ASK, Order::STATUS_QUOTED, Order::STATUS_SHELVE])
                              ->first();

                if (empty($order)) return true;
                //if ($order->order_status === Order::STATUS_DELIVERY_SUCCESS) return false;

                $this->updateOrderLogistics($order, $row);

                if (!empty($row['order_status'])) $orderService->sendSuccess($order, $row['deliver_time']);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                info('OrderDianxiaomiImport error', [['row' => $row], $e->getMessage(), $e->getFile(), $e->getLine()]);
            }
        }

        return [];
    }


    public function startRow(): int
    {
        return 2;
    }

    public function checkData($data): array
    {
        $error = [];
        $orderIds = $data->pluck('order_id')->toArray();
        $orderList = Order::query()
            ->whereIn('order_id', $orderIds)
            ->where('fulfillment_platform', ThirdPartyWarehouseConfig::PLATFORM_DIANXIAOMI)
            ->whereNot('order_status', Order::STATUS_SHELVE)
            ->select(['order_id', 'order_status', 'financial_status'])->get();
        $orderExistIds = $orderList->pluck('order_id')->toArray();

        $error['order_ids'] = [];

        //兼容拆单合单，订单号可能会重复，需要根据md5(订单号+sku)校验唯一
        $uniqueArray = [];
        foreach ($data as $datum) {
            $orderId = $datum['order_id'];
            $sku = implode(',', $datum['sku']);

            $uniqueKey = md5($orderId.$sku);

            //保存重复订单
            if (isset($uniqueArray[$uniqueKey])) {
                $error['order_ids'][] = $orderId;
                continue;
            }

            $uniqueArray[$uniqueKey] = $orderId;
        }

        if ($this->importType == ['order_info']) {
            $statusList = array_unique($data->pluck('order_status')->toArray());
            $statusArray = ['已发货'];
            $error['status_list'] = array_values(array_diff($statusList, $statusArray));
        } else {
            $error['status_list'] = [];
        }

        //判断订单是否已付款
        $noPaidList = [];
        $financialStatusList = $orderList->pluck('financial_status')->toArray();
        foreach ($financialStatusList as $key => $value) {
            if ($value != Order::FINANCIAL_STATUS_PAID) {
                $noPaidList[] = $orderExistIds[$key] ?? '';
            }
        }
        $error['no_paid_list'] = array_unique($noPaidList);

        //去掉物流渠道不一致的判断
        /*$channelList = $data->pluck('logistics_channel')->toArray();
        $channelExist = LogisticsChannelModel::query()->whereIn('name', $channelList)->select('name')->get()->pluck('name')->toArray();
        $error['logistics_channel_list'] = array_values(array_diff($channelList, $channelExist));*/

        return $error;
    }


    public function map($row): array
    {
        $data = [];
        if ($this->importType === 'order_info') {
            $countRow = 6;

            if (count($row) < $countRow) {
                throw new AccidentException('导入的模板格式不正确', Code::OPERATE_FAIL);
            }

            $row = array_slice($row, 0, $countRow);
            if (count(array_filter($row)) < count($row)) {
                throw new AccidentException('所有数据均为必填项不能为空', Code::OPERATE_FAIL);
            }

            [
                $orderId,
                $orderStatus,
                $sku,
                $logisticsChannel,
                $trackingNumber,
                $deliverTime
            ] = $row;

            // 兼容多种时间格式
            try {
                $deliverTime = Carbon::parse($deliverTime)->toDateTimeString();
            } catch (Exception $exception) {
                $dateObject = Date::excelToDateTimeObject($deliverTime);
                $deliverTime = $dateObject->format('Y-m-d H:i:s');
            }

            $data = [
                'order_id' => trim($orderId),
                'order_status' => trim($orderStatus),
                'sku' => explode("\n", trim($sku)),
                'logistics_channel' => trim($logisticsChannel),
                'tracking_number' => trim($trackingNumber),
                'deliver_time' => $deliverTime
            ];
        } else {
            $countRow = 4;

            if (count($row) < $countRow) {
                throw new AccidentException('导入的模板格式不正确', Code::OPERATE_FAIL);
            }

            $row = array_slice($row, 0, $countRow);
            if (count(array_filter($row)) < count($row)) {
                throw new AccidentException('所有数据均为必填项不能为空', Code::OPERATE_FAIL);
            }

            [
                $orderId,
                $sku,
                $logisticsChannel,
                $trackingNumber,
            ] = $row;

            $data = [
                'order_id' => trim($orderId),
                'sku' => explode("\n", trim($sku)),
                'logistics_channel' => trim($logisticsChannel),
                'tracking_number' => trim($trackingNumber),
            ];
        }

        return $data;
    }

    public function updateOrderLogistics($order, $orderDetail)
    {
        //创建订单包裹
        $expressOrderService = new ExpressOrderService(new ExpressOrderModel());
        $expressOrderService->createOrUpdateByDianxiaomiImport($order, $orderDetail, $this->importType);

        // 通知平台订单发货
        $sync_waybill_number = SystemConfigBaseService::getConfigValue(SystemConfig::SYNC_WAYBILL_NUMBER);
        if($sync_waybill_number == 1) {
            $order->packages->each(function ($package) {
                (new PackageBaseService($package))->packagePlatformDelivery();
            });
        }
    }
}
