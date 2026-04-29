<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminOperationLogService;
use App\Services\ApiResponseService;
use App\Http\Resources\Admin\AdminOperationLogList as ListResource;

class AdminOperationLogController extends Controller
{
    public AdminOperationLogService $service;

    public function __construct(AdminOperationLogService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $this->service->query->with('admin:id,name');

        return ListResource::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function optTypeList()
    {
        return ApiResponseService::success($this->service->model::optTypeList());
    }
}
