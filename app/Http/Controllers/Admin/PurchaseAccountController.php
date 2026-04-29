<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseAccountList;
use App\Services\Admin\PurchaseAccountService;
use App\Services\ApiResponseService;

class PurchaseAccountController extends Controller
{
    protected PurchaseAccountService $service;

    public function __construct(PurchaseAccountService $service)
    {
        $this->service = $service;
    }

    public function index(): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        return PurchaseAccountList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function getEnableAll()
    {
        return PurchaseAccountList::collection($this->service->getEnableAll())
            ->additional(ApiResponseService::success());
    }

    public function store(): \Illuminate\Http\JsonResponse|array
    {
        if($this->service->store()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function update($id)
    {
        if($this->service->update($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function enable($id)
    {
        if($this->service->enable($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function del($id)
    {
        if($this->service->del($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function cancel($id)
    {
        if($this->service->cancel($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
