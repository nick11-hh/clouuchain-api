<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\InboundOrderInfo;
use App\Http\Resources\Admin\InboundOrderList;
use App\Services\Admin\InboundOrderService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class InboundOrderController extends Controller
{
    protected InboundOrderService $service;

    public function __construct(InboundOrderService $service)
    {
        $this->service = $service;
    }


    public function index()
    {
        $list = $this->service->index();
        return InboundOrderList::collection($list)->additional(ApiResponseService::success());
    }


    public function show($id)
    {
        $data = $this->service->show($id);
        return InboundOrderInfo::make($data)->additional(ApiResponseService::success());
    }

    public function statusCount()
    {
        return ApiResponseService::success($this->service->statusCount());
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if ($this->service->store($request->all())) {
            return ApiResponseService::successMessage('保存成功');
        }
        return ApiResponseService::errorMessage('保存失败');
    }

    /**
     * @param $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::successMessage('保存成功');
        }
        return ApiResponseService::errorMessage('保存失败');
    }

    public function cancel($id)
    {
        if ($this->service->cancel($id)) {
            return ApiResponseService::successMessage('取消成功');
        }
        return ApiResponseService::errorMessage('取消失败');
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function deletes(Request $request)
    {
        if ($this->service->deletes($request->all())) {
            return ApiResponseService::successMessage('删除成功');
        }
        return ApiResponseService::errorMessage('删除失败');
    }

    /** 获取签收入库扫描数量
     * @param Request $request
     * @return InboundOrderInfo
     */
    public function scanData(Request $request)
    {
        $data = $this->service->scanData($request->all());
        return InboundOrderInfo::make($data)->additional(ApiResponseService::success());
    }

    /** 入库单签收
     * @param $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception
     */
    public function sign($id, Request $request)
    {
        if ($this->service->sign($id, $request->all())) {
            return ApiResponseService::successMessage('签收成功');
        }
        return ApiResponseService::errorMessage('签收失败');
    }

    /** 入库单上架入库
     * @param $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception
     */
    public function inbound($id, Request $request)
    {
        if ($this->service->inbound($id, $request->all())) {
            return ApiResponseService::successMessage('入库成功');
        }
        return ApiResponseService::errorMessage('入库失败');
    }

    public function printInboundItemsLabel()
    {
        return ApiResponseService::success($this->service->printInboundItemsLabel());
    }

}
