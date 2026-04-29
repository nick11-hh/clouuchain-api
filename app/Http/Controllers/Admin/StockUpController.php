<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\StockUpList;
use App\Services\Admin\OrderService;
use App\Services\Admin\StockUpService;
use App\Services\ApiResponseService;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StockUpController extends Controller
{
    use ValidatesRequests;

    public function __construct(public StockUpService $stockUpService)
    {
    }

    /**
     * 保存备货
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException|\App\Exceptions\AccidentException
     */
    public function saveStockUp(Request $request): JsonResponse|array
    {
        $requestData = $this->validate($request, [
            'stock_up_id' => 'nullable|integer', //备货id,可选
            'stock_up_describe' => 'required|max:255',//备货描述
            'custom_id' => 'required',//客户id
            'stock_up_type' => 'required|in:1,2',//备货类型:1=国内仓全款备货，2=海外仓全款备货
            'stock_up_product_item' => 'required|array|min:1',//产品项数组
            'stock_up_product_item.*.sku' => 'required|string',//SKU
            'stock_up_product_item.*.num' => 'required|integer|min:1',//数量
            'stock_up_product_item.*.price' => 'required|numeric|min:0',//单价
            'process' => 'required|in:0,1',//当前流程状态:0=暂存，1=审批中
        ], [
            'stock_up_id.required' => '备货ID不能为空',
            'stock_up_id.integer' => '备货ID必须为整数',
            'stock_up_describe.required' => '备货描述不能为空',
            'stock_up_describe.max' => '备货描述长度不能超过255个字符',
            'stock_up_product_item.required' => '请至少添加一个采购项',
            'stock_up_product_item.array' => '采购项格式不正确',
            'stock_up_product_item.min' => '请至少添加一个采购项',
            'stock_up_product_item.*.sku.required' => 'SKU不能为空',
            'stock_up_product_item.*.sku.string' => 'SKU必须为字符串',
            'stock_up_product_item.*.num.required' => '件数不能为空',
            'stock_up_product_item.*.num.integer' => '件数必须为整数',
            'stock_up_product_item.*.num.min' => '件数必须大于0',
            'stock_up_product_item.*.price.required' => '单价不能为空',
            'stock_up_product_item.*.price.numeric' => '单价必须为数字',
            'stock_up_product_item.*.price.min' => '单价必须大于0',
            'stock_up_product_item.*.warehouse.required' => '仓库名称不能为空',
            'stock_up_product_item.*.warehouse.string' => '仓库名称必须为字符串',
            'stock_up_product_item.*.warehouse.max' => '仓库名称长度不能超过255个字符',
            'purchase_opinion.required' => '采购意见不能为空',
            'purchase_opinion.string' => '采购意见必须为字符串',
            'purchase_opinion.max' => '采购意见长度不能超过255个字符',
        ]);

        $this->stockUpService->saveStockUp($requestData['stock_up_id'] ?? null, $requestData['stock_up_describe'], $requestData['custom_id'], $requestData['stock_up_type'], $requestData['stock_up_product_item'], $requestData['process']);
        return ApiResponseService::success();
    }

    /**
     * 保存采购备货
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException|\App\Exceptions\AccidentException
     */
    public function saveStockUpPurchase(Request $request): JsonResponse|array
    {
        $requestData = $this->validate($request, [
            'stock_up_id' => 'required|integer', // 备货ID
            'stock_up_purchase_item' => 'required|array|min:1', // 采购项数组必须
            'stock_up_purchase_item.*.order_num' => 'required|string|max:255', // 订单编号
            'stock_up_purchase_item.*.total' => 'required|numeric|min:0', // 采购金额必须为非负数
            'stock_up_purchase_item.*.sku' => 'required|string|max:255', // SKU
            'stock_up_purchase_item.*.num' => 'required|integer|min:1', // 件数必须为正整数
            'stock_up_purchase_item.*.warehouse' => 'required|string|max:255', // 仓库名称
            'purchase_opinion' => 'required|string|max:255', // 采购意见
        ], [
            'stock_up_id.required' => '备货ID不能为空',
            'stock_up_id.integer' => '备货ID必须为整数',
            'stock_up_purchase_item.required' => '请至少添加一个采购项',
            'stock_up_purchase_item.array' => '采购项格式不正确',
            'stock_up_purchase_item.min' => '请至少添加一个采购项',
            'stock_up_purchase_item.*.order_num.required' => '订单编号不能为空',
            'stock_up_purchase_item.*.order_num.string' => '订单编号必须为字符串',
            'stock_up_purchase_item.*.order_num.max' => '订单编号长度不能超过255个字符',
            'stock_up_purchase_item.*.total.required' => '采购金额不能为空',
            'stock_up_purchase_item.*.total.numeric' => '采购金额必须为数字',
            'stock_up_purchase_item.*.total.min' => '采购金额不能小于0',
            'stock_up_purchase_item.*.sku.required' => 'SKU不能为空',
            'stock_up_purchase_item.*.sku.string' => 'SKU必须为字符串',
            'stock_up_purchase_item.*.sku.max' => 'SKU长度不能超过255个字符',
            'stock_up_purchase_item.*.num.required' => '件数不能为空',
            'stock_up_purchase_item.*.num.integer' => '件数必须为整数',
            'stock_up_purchase_item.*.num.min' => '件数必须大于0',
            'stock_up_purchase_item.*.warehouse.required' => '仓库名称不能为空',
            'stock_up_purchase_item.*.warehouse.string' => '仓库名称必须为字符串',
            'stock_up_purchase_item.*.warehouse.max' => '仓库名称长度不能超过255个字符',
            'purchase_opinion.required' => '采购意见不能为空',
            'purchase_opinion.string' => '采购意见必须为字符串',
            'purchase_opinion.max' => '采购意见长度不能超过255个字符',
        ]);

        $this->stockUpService->saveStockUpPurchase($requestData['stock_up_id'], $requestData['stock_up_purchase_item'], $requestData['purchase_opinion']);
        return ApiResponseService::success();
    }

    /**
     * 删除备货
     * @param Request $request
     * @return array
     * @throws ValidationException
     * @throws \App\Exceptions\AccidentException
     */
    public function deleteStockUp(Request $request): array
    {
        $requestData = $this->validate($request, [
            'stock_up_id' => 'required|integer', // 备货ID
        ], [
            'stock_up_id.required' => '备货ID不能为空',
            'stock_up_id.integer' => '备货ID必须为整数',
        ]);

        $this->stockUpService->deleteStockUp($requestData['stock_up_id']);
        return ApiResponseService::success();
    }


    /**
     * 账务核账
     * @param Request $request
     * @return array
     * @throws ValidationException
     * @throws \App\Exceptions\AccidentException
     */
    public function accountingReconciliation(Request $request): array
    {
        $requestData = $this->validate($request, [
            'stock_up_id' => 'required|integer', // 备货ID
        ], [
            'stock_up_id.required' => '备货ID不能为空',
            'stock_up_id.integer' => '备货ID必须为整数',
        ]);

        $this->stockUpService->accountingReconciliation($requestData['stock_up_id']);
        return ApiResponseService::success();
    }

    /**
     * 验证企业微信回调
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function webhookVerification(Request $request)
    {
        Log::info('webhookVerification', [$request->all()]);
        $msgSignature = $request->query('msg_signature');
        $timestamp = $request->query('timestamp');
        $nonce = $request->query('nonce');
        $echostr = $request->query('echostr');
        return $this->stockUpService->verification($msgSignature, $timestamp, $nonce, $echostr);
    }

    /**
     * 企业微信回调
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function webhookApproval(Request $request): \Illuminate\Http\Response|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory
    {
        Log::info('webhookApproval', [$request->all()]);
        Log::info('Webhook XML Data', [
            'raw_xml' => $request->getContent(),
        ]);
        $msgSignature = $request->query('msg_signature');
        $timestamp = $request->query('timestamp');
        $nonce = $request->query('nonce');
        $rawXml = $request->getContent();
        if (empty($rawXml)) {
            return response('no content', 400);
        }
        return $this->stockUpService->approval($msgSignature, $timestamp, $nonce, $rawXml);
    }

    /**
     * 列表
     * @param Request $request
     * @return AnonymousResourceCollection
     * @throws ValidationException
     */
    public function list(Request $request): AnonymousResourceCollection
    {
        $validatedData = $this->validate($request, [
            'stock_up_number' => 'nullable|string|max:255',
            'stock_up_describe' => 'nullable|string|max:255',
            'customer_number' => 'nullable|string|max:255',
            'stock_up_type' => 'nullable|in:1,2',
            'product_sku' => 'nullable|string|max:255',
            'purchase_sku' => 'nullable|string|max:255',
            'submitter_id' => 'nullable|string|max:255',
            'process' => 'nullable|string', // 当前流程状态:0=暂存，1,2,5=进行中，3=已驳回，4=已取消，6=完成
            'node' => 'nullable|in:1,2,3,4',
            'begin_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'size' => 'nullable|string|min:1|max:200',
        ],[
            'stock_up_number.string' => '备货单号必须为字符串',
            'stock_up_number.max' => '备货单号长度不能超过255个字符',
            'stock_up_describe.string' => '备货描述必须为字符串',
            'stock_up_describe.max' => '备货描述长度不能超过255个字符',
            'customer_number.string' => '客户编号必须为字符串',
            'customer_number.max' => '客户编号长度不能超过255个字符',
            'product_sku.string' => '产品SKU必须为字符串',
            'product_sku.max' => '产品SKU长度不能超过255个字符',
            'purchase_sku.string' => '采购SKU必须为字符串',
            'purchase_sku.max' => '采购SKU长度不能超过255个字符',
            'submitter_id.string' => '提交人ID必须为字符串',
            'submitter_id.max' => '提交人ID长度不能超过255个字符',
            'process.string' => '流程状态必须为字符串',
            'process.in' => '流程状态只能为0,1,2,5,3,4,6',
            'node.string' => '节点必须为字符串',
            'node.in' => '节点只能为1,2,3,4',
            'begin_date.date_format' => '开始时间格式错误',
            'end_date.date_format' => '结束时间格式错误',
            'size.string' => '分页大小必须为字符串',
            'size.min' => '分页大小不能小于1',
            'size.max' => '分页大小不能超过200个字符',
        ]);
        $result = $this->stockUpService->list($validatedData);
        return StockUpList::collection($result)->additional(ApiResponseService::success());
    }


    /**
     * 流程列表
     * @param Request $request
     * @return int[]
     */
    public function process(Request $request): array
    {
        $data = $this->stockUpService->process();
        return ApiResponseService::success($data);
    }


    /**
     * 导出
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/8 16:04
     */
    public function export(Request $request)
    {
        $validatedData = $this->validate($request, [
            'stock_up_number' => 'nullable|string|max:255',
            'stock_up_describe' => 'nullable|string|max:255',
            'customer_number' => 'nullable|string|max:255',
            'stock_up_type' => 'nullable|in:1,2',
            'product_sku' => 'nullable|string|max:255',
            'purchase_sku' => 'nullable|string|max:255',
            'submitter_id' => 'nullable|string|max:255',
            'process' => 'nullable|string', // 当前流程状态:0=暂存，1,2,5=进行中，3=已驳回，4=已取消，6=完成
            'node' => 'nullable|in:1,2,3,4',
            'begin_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d',
            'size' => 'nullable|string|min:1|max:200',
        ],[
            'stock_up_number.string' => '备货单号必须为字符串',
            'stock_up_number.max' => '备货单号长度不能超过255个字符',
            'stock_up_describe.string' => '备货描述必须为字符串',
            'stock_up_describe.max' => '备货描述长度不能超过255个字符',
            'customer_number.string' => '客户编号必须为字符串',
            'customer_number.max' => '客户编号长度不能超过255个字符',
            'product_sku.string' => '产品SKU必须为字符串',
            'product_sku.max' => '产品SKU长度不能超过255个字符',
            'purchase_sku.string' => '采购SKU必须为字符串',
            'purchase_sku.max' => '采购SKU长度不能超过255个字符',
            'submitter_id.string' => '提交人ID必须为字符串',
            'submitter_id.max' => '提交人ID长度不能超过255个字符',
            'process.string' => '流程状态必须为字符串',
            'process.in' => '流程状态只能为0,1,2,5,3,4,6',
            'node.string' => '节点必须为字符串',
            'node.in' => '节点只能为1,2,3,4',
            'begin_date.date_format' => '开始时间格式错误',
            'end_date.date_format' => '结束时间格式错误',
            'size.string' => '分页大小必须为字符串',
            'size.min' => '分页大小不能小于1',
            'size.max' => '分页大小不能超过200个字符',
        ]);

        $data = $this->stockUpService->export($validatedData);
        return ApiResponseService::successMessage('导出任务添加成功，请到顶部订单下载管理中查看进度和下载');

    }

}
