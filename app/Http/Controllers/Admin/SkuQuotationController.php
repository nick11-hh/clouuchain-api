<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\OrderList;
use App\Http\Resources\Admin\PurchaseOrderList;
use App\Http\Resources\Admin\PurchaseOrderInfo;
use App\Services\Admin\PurchaseOrderService;
use App\Services\Admin\SkuQuotationService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class SkuQuotationController extends Controller
{
    public SkuQuotationService $service;

    public function __construct(SkuQuotationService $service)
    {
        $this->service = $service;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function index(Request $request): array
    {
        return ApiResponseService::success($this->service->getQuotationList($request->all()));
    }

    public function getNewQuotation(Request $request): array
    {
        return ApiResponseService::success($this->service->getNewQuotation($request->all()));
    }

    public function getHistoryQuotation(Request $request): array
    {
        return ApiResponseService::success($this->service->getHistoryQuotation($request->all()));
    }

    public function getCustomQuotation(): array
    {
        return ApiResponseService::success($this->service->getCustomQuotation());
    }

    public function saveSkuQuotation(Request $request){
        if($this->service->saveSkuQuotation($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    public function deleteQuotation(Request $request){
        if($this->service->deleteQuotation($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
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

    public function saveCustomQuotation(Request $request){
        if($this->service->saveCustomQuotation($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

}
