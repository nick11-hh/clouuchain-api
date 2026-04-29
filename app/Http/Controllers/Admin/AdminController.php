<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminList as ListResource;
use App\Services\Admin\AdminService;
use App\Services\ApiResponseService;

class AdminController extends Controller
{
    public $service;
    public function __construct(AdminService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return ListResource::collection($this->service->index())
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

    public function deletes()
    {
        if($this->service->deletes()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function enable()
    {
        if($this->service->enable()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function modifyPass($id)
    {
        if($this->service->modifyPass($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
