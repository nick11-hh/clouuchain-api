<?php

namespace App\Services\ThirdPartyWarehouse;

use App\Jobs\MabangCheckOrderPushStatusJob;
use App\Lib\Code;
use App\Models\ClientGoodsPublishLog;
use App\Models\Order;
use App\Models\OrderThirdPartyFulfillmentLogs;
use App\Models\ShopModel;
use App\Models\ShopOrderLogs;
use App\Models\ThirdPartyWarehouseConfig;
use App\Services\Admin\OrderService;
use App\Services\Admin\PackageService;
use App\Services\Base\SystemConfigService as SystemConfigBaseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class ThirdPartyWarehouseService
{

    public ThirdPartyWarehouseConfig $config;

    public ThirdPartyWarehouseInterface $platformService;

    /**
     * @param ThirdPartyWarehouseConfig|null $config
     * @throws Exception
     */
    public function __construct(ThirdPartyWarehouseConfig $config = null)
    {
        if (empty($config)) $config = ThirdPartyWarehouseConfig::getConfig();
        if (empty($config)) return throw new AccidentException('当前未配置或启用履约的ERP系统，请到"配置-ERP对接配置"里配置', Code::OPERATE_FAIL);
        $this->config = $config;
        if (!empty($config->id)) {
            $this->platformService = Factory::create($config);
        }
    }

    /**
     * @param $order
     * @return bool
     * @throws Exception
     */
    public function pushOrderToWarehouse($order): bool
    {
        $pushLog = $this->addPushLog($order);
        try {
            $result = $this->platformService->pushOrderToWarehouse($order);
            if ($result) {
                $order->fulfillment_platform = $this->config->platform;
                $order->fulfillment_push_status = Order::FULFILLMENT_PUSH_WAITING;
                $order->save();
                dispatch(new MabangCheckOrderPushStatusJob($order, $pushLog))->onQueue('third-party-warehouse');
                return true;
            }
        } catch (Exception $exception) {
            $order->fulfillment_push_status = Order::FULFILLMENT_PUSH_ERROR;
            ShopOrderLogs::addLog(['order_id' => $order->id, 'operator_type' => ShopOrderLogs::OPERATOR_TYPE_PUSH_THIRD_PARTY_ERROR, 'content' => '推送失败' . $exception->getMessage()]);
            $pushLog->status = OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_ERROR;
            $pushLog->content = $exception->getMessage();
            $pushLog->save();
            throw new AccidentException($exception->getMessage(), Code::OPERATE_FAIL);
        }
        return false;
    }

    /**
     * @param $order
     * @return mixed
     */
    public function syncOrderSendStatus($order)
    {
        $result = $this->platformService->syncOrderSendStatus($order);
        return $this->processSyncResult($order, $result);
    }

    /**
     * 批量同步订单发货状态（每批最多10个订单）
     * @param \Illuminate\Support\Collection $orders 订单集合
     * @return array 处理结果
     */
    public function batchSyncOrderSendStatus($orders): array
    {
        // 批量获取订单详情并同步状态
        $results = $this->platformService->batchSyncOrderSendStatus($orders);

        $processedResults = [];
        foreach ($orders as $order) {
            $result = $results[$order->order_id] ?? ['is_send' => false, 'data' => []];
            try {
                $processedResults[$order->order_id] = $this->processSyncResult($order, $result);
            } catch (\Exception $e) {
                // 添加详细的错误跟踪日志
                info('批量同步订单状态处理失败', [
                    'order_id' => $order->order_id ?? 'N/A',
                    'order_db_id' => $order->id ?? 'N/A',
                    'order_status' => $order->order_status ?? 'N/A',
                    'fulfillment_platform' => $order->fulfillment_platform ?? 'N/A',
                    'packages_count' => $order->packages->count() ?? 0,
                    'has_packages' => !$order->packages->isEmpty(),
                    'error_message' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'error_trace' => $e->getTraceAsString(),
                    'result_data' => $result ?? null,
                ]);
                $processedResults[$order->order_id] = null;
            }
        }
        return $processedResults;
    }

    /**
     * 处理同步结果（内部复用方法）
     * @param $order
     * @param array $result
     * @return mixed
     */
    protected function processSyncResult($order, array $result)
    {
        return DB::transaction(function () use ($result, $order) {
            if ($result['is_send'] && $order->order_status === Order::STATUS_PENDING) {
                ShopOrderLogs::addLog([
                    'order_id' => $order->id,
                    'operator_type' => ShopOrderLogs::OPERATOR_TYPE_SYNC_THIRD_PARTY_TRACK_NUMBER,
                    'content' => '发货成功，马帮系统已发货，同步更新订单状态',
                    'operator_id' => 0
                ]);
                static $packageService = null;
                if ($packageService === null) {
                    $packageService = new PackageService();
                }
                $order->order_status = Order::STATUS_APPLY_NUM;
                $order->save();
                $order->packages->each(function ($package) use ($packageService) {
                    $packageService->outbound($package);
                });
            }
            return $result['data'];
        });
    }

    /**
     * 更新订单数据
     * @param $order
     * @param $type
     * @param array $updateData
     * @return true
     * @throws Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/20 17:13
     */
    public function updateOrderData($order, $type = 1, $extraUpdateData = [])
    {
        $typeName = OrderThirdPartyFulfillmentLogs::getUpdateTypeName($type);
        $pushLog = $this->addPushLog($order);

        try {
            $result = $this->platformService->updateOrderData($order, $type, $extraUpdateData);

            $pushLog->status = OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_SUCCESS;
            $pushLog->content = $typeName . '成功';
            $pushLog->save();

            return true;
        } catch (Exception $e) {
            $pushLog->status = OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_ERROR;
            $pushLog->content = $typeName. '失败，错误信息：' .$e->getMessage();
            $pushLog->save();
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    protected function addPushLog($order)
    {
        return OrderThirdPartyFulfillmentLogs::query()->create([
            'order_id' => $order->order_id,
            'platform_order_no' => $order->name,
            'order_info' => "平台订单号：{$order->order_id} / 平台编号：{$order->name}",
            'platform' => $this->config->platform,
            'operate_id' => auth('admin')->id() ?: 0,
        ]);
    }

    //新增马帮库存sku
    public function addMabangStock(array $goodsInfo)
    {
        return $this->platformService->addMabangStock($goodsInfo);
    }

    //修改马帮库存sku
    public function updateMabangStock(array $params)
    {
        return $this->platformService->updateMabangStock($params);
    }

    //商品链接绑定马帮库存sku
    public function skuLinkBindMabangStock(array $params)
    {
        return $this->platformService->skuLinkBindMabangStock($params);
    }

    public function addMabangComboSku($goodsSku)
    {
        $pushLog = $this->addProductLog($goodsSku);

        try {
            $result = $this->platformService->addMabangComboSku($goodsSku);
            $pushLog->status = OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_SUCCESS;
            $pushLog->content = '推送组合品到马帮成功';
            $pushLog->save();
            $goodsSku->mabang_stock_id = $result['mabang_stock_id'];
            $goodsSku->save();
            return true;
        } catch (Exception $e) {
            $pushLog->status = OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_SUCCESS;
            $pushLog->content = '推送组合品到马帮失败';
            $pushLog->save();
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
    }

    public function updateMabangComboSku($goodsSku)
    {
        $pushLog = $this->addProductLog($goodsSku);

        try {
            $this->platformService->updateMabangComboSku($goodsSku);
            $pushLog->status = OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_SUCCESS;
            $pushLog->content = '更新组合品到马帮成功';
            $pushLog->save();
            return true;
        } catch (Exception $e) {
            $pushLog->status = OrderThirdPartyFulfillmentLogs::FULFILLMENT_PUSH_ERROR;
            $pushLog->content = '更新组合品到马帮失败' . $e->getMessage();
            $pushLog->save();
            throw new AccidentException($e->getMessage(), Code::OPERATE_FAIL);
        }
    }


    public function addProductLog($goodsSku)
    {
        return OrderThirdPartyFulfillmentLogs::query()->create([
            'order_id' => $goodsSku->sku_id,
            'platform_order_no' => $goodsSku->goods?->spu,
            'order_info' => '产品SKU：' . $goodsSku->sku_id . '，规格：' . $goodsSku->spec_name,
            'platform' => ThirdPartyWarehouseConfig::PLATFORM_MABANG,
            'operate_id' => auth('admin')->id() ?: 0,
            'type_id' => OrderThirdPartyFulfillmentLogs::TYPE_GOODS,
        ]);
    }

}
