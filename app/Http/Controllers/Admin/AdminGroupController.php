<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminGroupList as ListResource;
use App\Http\Resources\Admin\RouteMenuList;
use App\Services\Admin\AdminGroupService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminGroupController extends Controller
{
    public AdminGroupService $service;

    public function __construct(AdminGroupService $service)
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

    public function deletes(): JsonResponse|array
    {
        if($this->service->deletes()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function getPermissions(int $id)
    {
        return RouteMenuList::collection($this->service->getPermissions($id))
            ->additional(ApiResponseService::success());
    }

    public function updatePermissions(Request $request, int $id)
    {
        if ($this->service->updatePermissions($id, $request->only('permissions'))) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
