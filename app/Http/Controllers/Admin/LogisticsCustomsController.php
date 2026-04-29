<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LogisticsCustomsList;
use App\Services\Admin\LogisticsCustomsService;
use App\Services\ApiResponseService;

class LogisticsCustomsController extends Controller
{
    public LogisticsCustomsService $service;
    public function __construct(LogisticsCustomsService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return LogisticsCustomsList::collection($this->service->index())
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

    public function del($id)
    {
        if($this->service->del($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
