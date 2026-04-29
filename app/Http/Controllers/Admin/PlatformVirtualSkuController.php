<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PlatformVirtualSkuList;
use App\Services\Admin\PlatformVirtualSkusService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformVirtualSkuController extends Controller
{
    public PlatformVirtualSkusService $service;

    public function __construct(PlatformVirtualSkusService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return PlatformVirtualSkuList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function store(Request $request)
    {
        if($this->service->store($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function storeByOrderItem($id)
    {
        if($this->service->storeByOrderItem($id)) {
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
}
