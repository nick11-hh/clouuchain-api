<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminList;
use App\Http\Resources\Admin\CustomList;
use App\Services\Admin\PermissionService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Shopify\Rest\Admin2022_04\Customer;

class PermissionController extends Controller
{
    protected PermissionService $service;

    public function __construct(PermissionService $service)
    {
        $this->service = $service;
    }


    public function staff()
    {
        $list = $this->service->staff();
        return AdminList::collection($list)->additional(ApiResponseService::success());
    }

    public function customer()
    {
        $list = $this->service->customer();
        return CustomList::collection($list)->additional(ApiResponseService::success());
    }

    public function assignDataPermissions(Request $request)
    {
        if ($this->service->assignDataPermissions($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    public function removeDataPermissions(Request $request)
    {
        if ($this->service->removeDataPermissions($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

}
