<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\InboundOrderInfo;
use App\Http\Resources\Admin\InboundOrderList;
use App\Http\Resources\Admin\OutboundOrderInfo;
use App\Http\Resources\Admin\OutboundOrderList;
use App\Services\Admin\InboundOrderService;
use App\Services\Admin\OutboundOrderService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class OutboundOrderController extends Controller
{
    protected OutboundOrderService $service;

    public function __construct(OutboundOrderService $service)
    {
        $this->service = $service;
    }


    public function index()
    {
        $list = $this->service->index();
        return OutboundOrderList::collection($list)->additional(ApiResponseService::success());
    }


    public function show($id)
    {
        $data = $this->service->show($id);
        return OutboundOrderInfo::make($data)->additional(ApiResponseService::success());
    }

    /** 扫描单号数据
     * @param Request $request
     * @return OutboundOrderInfo
     * @throws \App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception
     */
    public function scanData(Request $request)
    {
        $data = $this->service->scanData($request->all());
        return OutboundOrderInfo::make($data)->additional(ApiResponseService::success());
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

    /** 更新包裹重量和体积信息
     * @param $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function updatePackageInfo($id, Request $request)
    {
        if ($this->service->updatePackageInfo($id, $request->all())) {
            return ApiResponseService::successMessage('更新成功');
        }
        return ApiResponseService::errorMessage('更新失败');
    }

    /** 订单出库
     * @param $id
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function outbound($id)
    {
        if ($this->service->outbound($id)) {
            return ApiResponseService::successMessage('出库成功');
        }
        return ApiResponseService::errorMessage('出库失败');
    }

    /**
     * @desc 称重完成
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function weighingCompleted()
    {
        if ($this->service->weighingCompleted()) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage('操作失败');
    }

}
