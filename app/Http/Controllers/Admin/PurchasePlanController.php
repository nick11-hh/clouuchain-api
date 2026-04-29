<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchasePlanList;
use App\Services\Admin\PurchasePlanService;
use App\Services\ApiResponseService;

class PurchasePlanController extends Controller
{
    public PurchasePlanService $service;
    public function __construct(PurchasePlanService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return PurchasePlanList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }


    public function show($id)
    {
        return PurchasePlanList::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }


    public function store()
    {
        $params = request()->all();
        $params['create_user_id'] = auth()->id();

        if($this->service->store($params)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function cancel()
    {
        if($this->service->cancel()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function statusCount()
    {
        return ApiResponseService::success($this->service->statusCount());
    }

    public function getPlanSku()
    {
        return ApiResponseService::success($this->service->getPlanSku());
    }

    public function getSuppliersByGoodsSkuId($sku_id)
    {
        return ApiResponseService::success($this->service->getSuppliersByGoodsSkuId($sku_id));
    }

    public function updateStatus()
    {
        if($this->service->updateStatus(request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
