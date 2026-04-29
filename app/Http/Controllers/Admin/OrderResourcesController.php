<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResourceList;
use App\Services\Admin\OrderResources;
use App\Services\ApiResponseService;

class OrderResourcesController extends Controller
{
    protected OrderResources $service;
    public function __construct(OrderResources $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return OrderResourceList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function store()
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

    public function del()
    {
        if($this->service->del()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function submit($id)
    {
        if($this->service->submit($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function statusCount()
    {
        return ApiResponseService::success($this->service->statusCount());
    }

    public function claim()
    {
        if($this->service->claim()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function allocation()
    {
        if($this->service->allocation()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function markStatus()
    {
        if($this->service->markStatus()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function quotation()
    {
        if($this->service->quotation()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function submitQuotation()
    {
        if($this->service->submitQuotation()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
