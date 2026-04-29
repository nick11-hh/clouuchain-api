<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\InboundOrderInfo;
use App\Http\Resources\Admin\InboundOrderList;
use App\Http\Resources\Admin\OutboundOrderInfo;
use App\Http\Resources\Admin\OutboundOrderList;
use App\Http\Resources\Admin\PickingOrderInfo;
use App\Http\Resources\Admin\PickingOrderList;
use App\Services\Admin\InboundOrderService;
use App\Services\Admin\OutboundOrderService;
use App\Services\Admin\PickingOrderService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class PickingOrderController extends Controller
{
    protected PickingOrderService $service;

    public function __construct(PickingOrderService $service)
    {
        $this->service = $service;
    }


    public function index()
    {
        $list = $this->service->index();
        return PickingOrderList::collection($list)->additional(ApiResponseService::success());
    }


    public function show($id)
    {
        $data = $this->service->show($id);
        return PickingOrderInfo::make($data)->additional(ApiResponseService::success());
    }

    public function statusCount()
    {
        return ApiResponseService::success($this->service->statusCount());
    }

    /** 扫描拣货单号数据
     * @param Request $request
     * @return PickingOrderInfo
     * @throws \App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception
     */
    public function scanData(Request $request)
    {
        $data = $this->service->scanData($request->all());
        return PickingOrderInfo::make($data)->additional(ApiResponseService::success());
    }

    public function printPicking($id)
    {
        return $this->service->printPicking($id);
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function createByOutboundIds(Request $request)
    {
        if ($this->service->createByOutboundIds($request->all())) {
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

    public function assignStaff(Request $request)
    {
        if ($this->service->assignStaff($request->all())) {
            return ApiResponseService::successMessage('分配成功');
        }
        return ApiResponseService::errorMessage('分配失败');
    }

    public function secondSort($id, Request $request)
    {
        if ($this->service->secondSort($id, $request->all())) {
            return ApiResponseService::successMessage('订单分拣成功');
        }
        return ApiResponseService::errorMessage('订单分拣失败');
    }

    /** 完成拣货单
     * @param $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception
     */
    public function completePicking($id)
    {
        if ($this->service->completePicking($id)) {
            return ApiResponseService::successMessage('拣货单分拣完成');
        }
        return ApiResponseService::errorMessage('拣货单分拣失败');
    }



}
