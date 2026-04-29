<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\OrderList;
use App\Http\Resources\Admin\PurchaseOrderList;
use App\Http\Resources\Admin\PurchaseOrderInfo;
use App\Http\Resources\Admin\PurchaseOrderLogs;
use App\Services\Admin\PurchaseOrderService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public PurchaseOrderService $service;

    public function __construct(PurchaseOrderService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return PurchaseOrderList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function show($id)
    {
        return PurchaseOrderInfo::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }


    public function store(Request $request)
    {
        if($this->service->createPurchase($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function storeBackups(Request $request)
    {
        if($this->service->createPurchase($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }


    public function getScanData()
    {
        return PurchaseOrderInfo::make($this->service->getScanData())
            ->additional(ApiResponseService::success());
    }

    public function markOrder()
    {
        if($this->service->markOrder()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function setUrl()
    {
        if($this->service->setUrl()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function setShipmentInfo()
    {
        if($this->service->setShipmentInfo()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function statusCount()
    {
        return ApiResponseService::success($this->service->statusCount());
    }

    public function confirm()
    {
        if($this->service->confirm()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function autoPurchase($id)
    {
        if($this->service->autoPurchase($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }


    /** 采购单余货入库
     * @param $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function purchaseStockInStorage($id, Request $request)
    {
        if($this->service->purchaseStockInStorage($id, $request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }


    public function getMatchOrderList($id)
    {
        return OrderList::collection($this->service->getMatchOrderList($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function orderDeliverByPurchase(Request $request)
    {
        if($this->service->orderDeliverByPurchase($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function syncPurchaseOrdersStatus(Request $request)
    {
        if($this->service->syncPurchaseOrdersStatus($request->input('ids'))) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    public function updateStatus()
    {
        if($this->service->updateStatus()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function getPurchaseOrderLogs()
    {
        return PurchaseOrderLogs::collection($this->service->getPurchaseOrderLogs())
            ->additional(ApiResponseService::success());
    }

}
