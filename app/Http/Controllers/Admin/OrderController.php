<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\OrderInfo;
use App\Http\Resources\Admin\OrderList;
use App\Http\Resources\Admin\OrderQuoteInfo;
use App\Http\Resources\Admin\OrderThirdPartyFulfillmentLogList;
use App\Http\Resources\Admin\ShopOrderList;
use App\Http\Resources\Admin\StockOrderList;
use App\Lib\Code;
use App\Models\ExchangeRateModel;
use App\Models\Order;
use App\Models\OrderLineItem;
use App\Models\OrderThirdPartyFulfillmentLogs;
use App\Models\ShopOrderAbnormal;
use App\Services\Admin\OrderExportService;
use App\Services\Admin\OrderService;
use App\Services\ApiResponseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderController extends Controller
{
    public OrderService $service;

    public function __construct(OrderService $service)
    {
        $this->service = $service;
    }

    public function test()
    {
        return $this->service->test();
    }

    public function index()
    {
        $orders = $this->service->index();

        $orders->each(function ($order) {
            if ($order->order_status < 3 && $order->favourable_price === '-1.00') {
                $order->favourable_price = 0;
            }
            if ($order->prop_id == 0 && $order->allLineItems->isNotEmpty()) {
                $lineItems  = $order->allLineItems;
                // 情况 1：只有一个 lineItem
                if ($lineItems->count() == 1) {
                    $firstItem = $lineItems->first();

                    if ($firstItem && !empty($firstItem->mapping) && !empty($firstItem->mapping->goodsSku)) {
                        $order->prop_id = $firstItem->mapping->goodsSku->prop_id;
                        $total_weight = $firstItem->quantity * ($firstItem->mapping->goodsSku->weight ?? 0);
                        $order->total_weight = $total_weight / 1000;
                        $order->save();
                    }

                    return; // 单条订单处理完毕
                }

                // 情况 2：多个 lineItem
                $hasNullMapping = $lineItems->contains(function ($item) {
                    return empty($item->mapping) || empty($item->mapping->goodsSku);
                });

                if ($hasNullMapping) {
                    return; // 存在空 mapping，跳过该订单
                }
                // 提取所有有效 prop_id
                $propIds = $lineItems
                    ->map(fn($item) => $item->mapping->goodsSku->prop_id ?? 0)
                    ->filter(fn($id) => $id > 0)
                    ->toArray();
                // 计算订单总重量
                $total_weight = $lineItems->reduce(function ($carry, $item) {
                    $weight = $item->mapping->goodsSku->weight ?? 0;
                    return $carry + ($item->quantity * $weight);
                }, 0);

                if (!empty($propIds)) {
                    $order->prop_id = max($propIds);
                }
                $order->total_weight = $total_weight / 1000; // 转换为 kg
            }
            $exchange_rates = ExchangeRateModel::query()->where('currency_code', 'CNY')
                ->value('custom_exchange_rate');
            $order->exchange_rates = $exchange_rates;
            $order->save();
        });
       return ShopOrderList::collection($orders)->additional(ApiResponseService::success());
    }

    public function batchMatchingLogistics(Request $request)
    {
        if ($result = $this->service->batchMatchingLogistics($request->all())) {
            return ApiResponseService::success($result);
        }
        return ApiResponseService::error();
    }

    public function show($id)
    {
        return OrderInfo::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    public function orderQuoteInfo($id)
    {
        return ApiResponseService::success($this->service->orderQuoteInfo($id));
    }

    public function quote()
    {
        if($this->service->quote()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function setQuote()
    {
        if($this->service->setQuote()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function setPaymented()
    {
        return $this->service->setPaymented();
    }

    public function setLogistics()
    {
        if($this->service->setLogistics()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function scanOrder()
    {
        return OrderList::collection($this->service->scanOrder())
            ->additional(ApiResponseService::success());
    }

    public function removePrint()
    {
        if($this->service->removePrint()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function printLable($id)
    {
        return ApiResponseService::success($this->service->printLable($id));
    }

    public function mergePdf()
    {
        return ApiResponseService::success($this->service->mergePdf());
    }

    public function printLogisticsLable(): array
    {
        return ApiResponseService::success($this->service->printLogisticsLable());
    }

    public function printSendLable()
    {
        return ApiResponseService::success($this->service->printSendLable());
    }

    public function printLogisticsSendLable()
    {
        return ApiResponseService::success($this->service->printLogisticsSendLable());
    }

    public function createFulfillment($id)
    {
        if($this->service->createFulfillment($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function requestFulfillment($id)
    {
        return ApiResponseService::success($this->service->requestFulfillment($id));
    }

    public function handleSend()
    {
        if($this->service->handleSend()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function statusCount()
    {
        return ApiResponseService::success($this->service->statusCount());
    }

    public function changeStatusCount()
    {
        return ApiResponseService::success($this->service->changeStatusCount());
    }

    public function changeLogistics()
    {
        if($this->service->changeLogistics()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function updateTracking()
    {
        if($this->service->updateTracking()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function openFulfillment($id)
    {
        return ApiResponseService::success($this->service->openFulfillment($id));
    }

    public function cancelFulfillment($id)
    {
        return ApiResponseService::success($this->service->cancelFulfillment($id));
    }

    public function pullOrders(Request $request)
    {
        $data = $this->service->pullOrders($request->all());
        if($data) {
            return ApiResponseService::successMessage($data['message'], $data);
        }

        return ApiResponseService::error();
    }

    public function getChannelByOrderId($id)
    {
        return ApiResponseService::success($this->service->getChannelByOrderId($id));
    }

    /**
     * @param $orderId
     * @param Request $request
     * @return array|JsonResponse
     * @throws Throwable
     */
    public function orderMappingQuote($orderId, Request $request)
    {
        if($this->service->orderMappingQuote($orderId, $request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    /**
     * 将待打单中的缺货移入有货
     * @return array|JsonResponse
     * @throws Exception|Throwable
     */
    public function moveToInStock(): array|JsonResponse
    {
        if($this->service->moveToInStock()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 将待打单中的有货移入缺货
     * @return array|JsonResponse
     * @throws Throwable
     * @throws ValidationException
     */
    public function moveToOutStock(): array|JsonResponse
    {
        if($this->service->moveToOutStock()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function updateShippingAddr($id): JsonResponse|array
    {
        if($this->service->updateShippingAddr($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function updateDeclaration($order_item_id)
    {
        if($this->service->updateDeclaration($order_item_id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param OrderExportService $service
     * @return JsonResponse|array
     */
    public function export(OrderExportService $service): JsonResponse|array
    {
        if ($service->export()) {
            return ApiResponseService::success(message: '导出任务添加成功,请到顶部订单下载管理中查看进度和下载');
        }

        return ApiResponseService::error();
    }

    public function import()
    {
        return ApiResponseService::success($this->service->import());
    }

    public function handMovement($id)
    {
        if($this->service->handMovement($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function updateHandCustoms($id)
    {
        if($this->service->updateHandCustoms($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function addOrderRemark($id, Request $request)
    {
        if($this->service->addOrderRemark($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function vendorChangePrice(Request $request)
    {
        if($this->service->vendorChangePrice($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function getPaymentInfo()
    {
        return ApiResponseService::success($this->service->getPaymentInfo());
    }

    public function getExchangeRatePrice()
    {
        return ApiResponseService::success($this->service->getExchangeRatePrice());
    }

    public function getExchangeRate()
    {
        return ApiResponseService::success($this->service->getExchangeRate());
    }

    public function deleteItems()
    {
        if ($this->service->deleteItems()) {
            return ApiResponseService::success();
        }
        return ApiResponseService::errorMessage();
    }

    public function restoreItems()
    {
        if ($this->service->restoreItems()) {
            return ApiResponseService::success();
        }
        return ApiResponseService::errorMessage();
    }

    public function getSkuQuotation()
    {
        return ApiResponseService::success($this->service->getSkuQuotation());
    }

    public function getStockOrderList()
    {
        return StockOrderList::collection($this->service->getStockOrderList())
           ->additional(ApiResponseService::success());
    }

    public function stockOrderStatusCount()
    {
        return ApiResponseService::success($this->service->stockOrderStatusCount());
    }

    public function stockOrderToInbound()
    {
        return ApiResponseService::success($this->service->stockOrderToInbound());
    }

    public function batchUpdateDeclaration()
    {
        if($this->service->batchUpdateDeclaration()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function setShelve()
    {
        return ApiResponseService::success($this->service->setShelve());
    }

    public function cancelShelve()
    {
        return ApiResponseService::success($this->service->cancelShelve());
    }

    public function setNotShipping()
    {
        if ($this->service->setNotShipping()) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error('操作失败');
    }

    public function cancelNotShipping()
    {

        if ($this->service->cancelNotShipping()) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error('操作失败');
    }

    public function batchUpdateShippingAddress()
    {
        if($this->service->batchUpdateShippingAddress()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function assignStaff()
    {
        return ApiResponseService::success($this->service->assignStaff());
    }

    public function pushThirdPartyWarehouse(Request $request)
    {
        if ($this->service->pushThirdPartyWarehouse($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function getFulfillmentPushLogs(Request $request)
    {
        return OrderThirdPartyFulfillmentLogList::collection($this->service->getFulfillmentPushLogs($request->all()))
            ->additional(ApiResponseService::success());
    }


    public function exportDianxiaomiOrder(Request $request)
    {
        if ($this->service->exportDianXiaoMiOrderNew()) {
            return ApiResponseService::successMessage('导出任务添加成功,请到顶部订单下载管理中查看进度和下载');
        }
        return ApiResponseService::errorMessage('导出失败');
    }


    public function batchUpdateFulfillmentPlatform(Request $request)
    {
        if ($this->service->batchUpdateFulfillmentPlatform($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }


    public function batchUpdateRemark(Request $request)
    {
        if ($this->service->batchUpdateRemark($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function importOrderLogistics(Request $request)
    {
        $result = $this->service->importOrderLogistics();
        if ($result['ret']) {
            return ApiResponseService::successMessage('导入数据成功');
        } else {
            return ApiResponseService::error(Code::CUSTOM_ERROR, '导入数据错误', $result['data']);
        }
    }


    /** 同第三方履约状态和运单号
     * @param Request $request
     * @return array|JsonResponse
     * @throws \App\Exceptions\AccidentException
     */
    public function syncThirdPartyWarehouse(Request $request)
    {
        if ($message = $this->service->syncThirdPartyWarehouse($request->all())) {
            return ApiResponseService::successMessage($message);
        }
        return ApiResponseService::error();
    }

    /**
     * 自动报价
     */
    public function autoQuotation(Request $request)
    {
        $data = $this->service->autoQuotation();
        if ($data) {
            if ($data['sync']) {
                $message = "操作成功，成功报价：{$data['success_count']}单，报价失败：{$data['error_count']}单";
            } else {
                $message = "操作成功，自动报价在后台执行，请关注后续变化";
            }
            return ApiResponseService::successMessage($message, $data);
        }
        return ApiResponseService::error();
    }

    /**
     * 打回报价中
     */
    public function orderRollbackQuote(Request $request)
    {
        if ($this->service->orderRollbackQuote($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    /**
     * 订单退款
     */
    public function orderRefund(Request $request)
    {
        if ($this->service->orderRefund($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function setDisableStatus()
    {
        return ApiResponseService::success($this->service->setDisableStatus());
    }

    /**
     * 导入订单新版
     * @return array
     * @throws Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/13 15:28
     */
    public function importNew()
    {
        return ApiResponseService::success($this->service->importNew());
    }

    /**
     * 补收费用
     */
    public function supplementFee(Request $request)
    {
        if ($this->service->supplementFee($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    /**
     * 订单取消
     * @param Request $request
     * @return array|JsonResponse
     * @throws Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/30 18:21
     */
    public function orderCancel(Request $request)
    {
        if($this->service->orderCancel($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 订单取消并退款
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/30 18:21
     */
    public function orderCancelAndRefund(Request $request)
    {
        if ($this->service->orderCancelAndRefund($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 订单取消撤回
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/30 19:15
     */
    public function orderCancelWithdraw(Request $request)
    {
        if($this->service->orderCancelWithdraw($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }


    /**
     * 同步平台发货
     * @param Request $request
     * @return array|JsonResponse
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/2/6 18:57
     */
    public function syncPlatformFulfillment(Request $request)
    {
        if($this->service->syncPlatformFulfillment($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 调整报价
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/10/25 11:42
     */
    public function changeQuotePrice(Request $request)
    {
        if ($this->service->changeQuotePrice($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 恢复已取消的订单(恢复成取消的状态)
     * @param Request $request
     * @return array|JsonResponse
     * @throws Throwable
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/21 17:21
     */
    public function restore(Request $request)
    {
        if ($this->service->restore($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 获取订单id合集
     * @param Request $request
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/27 16:50
     */
    public function getIds(Request $request)
    {
        return ApiResponseService::success($this->service->getIds());
    }

    /**
     * 移除平台异常状态
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/28 19:12
     */
    public function abnormalMoveToQuote(Request $request)
    {
        if($this->service->abnormalMoveToQuote($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 订单状态列表
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/28 19:13
     */
    public function dataJson()
    {
        $data = [
            'abnormal_reason'       => convertConstant(ShopOrderAbnormal::PLATFORM_ABNORMAL_LIST),
            'stock_status_list'     => convertConstant(Order::STOCK_STATUS_LIST),
            'logistics_status_list' => convertConstant(Order::LOGISTICS_STATUS_LIST),
        ];
        return ApiResponseService::success($data);
    }

    /**
     * 子状态数量统计
     * @param $type
     * @param Request $request
     * @return array
     * @throws Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/28 19:13
     */
    public function subStatusCount($type, Request $request)
    {
        return ApiResponseService::success($this->service->subStatusCount($type));
    }

    /**
     * 忽略订单异常（仅移除异常，不做任何其他操作）
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/7 14:18
     */
    public function ignoreAbnormal(Request $request)
    {
        if ($this->service->ignoreAbnormal($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }


    public function generationQuoteInfo($id, Request $request)
    {
        if($result = $this->service->generationQuoteInfo($id, $request->all())) {
            return ApiResponseService::success($result);
        }

        return ApiResponseService::error();
    }

    /**
     *  订单物流费用预估
     */
    public function shippingCostEstimate(Request $request)
    {
        if ($request = $this->service->shippingCostEstimate($request->all())) {
            return ApiResponseService::success($request);
        }
        return ApiResponseService::error();
    }

    public function orderAddLineItem($id, Request $request)
    {
        if($result = $this->service->orderAddLineItem($id, $request->all())) {
            return ApiResponseService::success($result);
        }

        return ApiResponseService::error();
    }

    /**
     * 更新订单商品数量
     */
    public function updateLineItem(Request $request)
    {
        if ($result = $this->service->updateLineItem($request->all())) {
            return ApiResponseService::success($result);
        }
        return ApiResponseService::error();
    }

    /** 订单归档
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     */
    public function archive(Request $request)
    {
        if($result = $this->service->archive($request->all())) {
            return ApiResponseService::success($result);
        }

        return ApiResponseService::error();
    }

    /** 订单取消归档
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     */
    public function rollbackArchive(Request $request)
    {
        if($result = $this->service->rollbackArchive($request->all())) {
            return ApiResponseService::success($result);
        }

        return ApiResponseService::error();
    }

    /**
     * @param $id
     * @return array|JsonResponse
     */
    public function setVirtual($id)
    {
        if($result = $this->service->setVirtual($id)) {
            return ApiResponseService::success($result);
        }

        return ApiResponseService::error();
    }

    /** 导入物流单号
     * @return array|JsonResponse
     */
    public function importLogistics()
    {
        if($this->service->importLogistics()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     * @throws \App\Exceptions\AccidentException
     */
    public function getLogisticsChannels(Request $request)
    {
        if ($result = $this->service->getLogisticsChannels($request->all())) {
            return ApiResponseService::success($result);
        }
        return ApiResponseService::error();
    }

    /**
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     */
    public function getQuotedAmount(Request $request)
    {
        if ($result = $this->service->getQuotedAmount($request->all())) {
            return ApiResponseService::success($result);
        }
        return ApiResponseService::error();
    }

    public function batchSaveQuotes(Request $request)
    {
        if ($result = $this->service->batchSaveQuotes($request->all())) {
            return ApiResponseService::success($result);
        }
        return ApiResponseService::error();
    }

}
