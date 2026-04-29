<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResourceList;
use App\Services\ApiResponseService;
use App\Services\Client\OrderResources;

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

    public function accept($id)
    {
        if($this->service->accept($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function batchChangeStatus()
    {
        if($this->service->batchChangeStatus()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

}
