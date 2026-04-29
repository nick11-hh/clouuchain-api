<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\InventoryStockInfo;
use App\Http\Resources\Admin\InventoryStockList;
use App\Services\Admin\InventoryStockService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class InventoryStockController extends Controller
{
    public InventoryStockService $service;

    public function __construct(InventoryStockService $service)
    {
        $this->service = $service;
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        return InventoryStockList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * @param $id
     * @return InventoryStockInfo
     */
    public function show($id)
    {
        return InventoryStockInfo::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    public function mapData()
    {
        return ApiResponseService::success($this->service->mapData());
    }

    public function store(Request $request)
    {
        if ($this->service->store($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    public function saveInventory($id, Request $request)
    {
        if ($this->service->saveInventory($id, $request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    public function submitInventory($id, Request $request)
    {
        if ($this->service->submitInventory($id, $request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    public function cancel($id)
    {
        if ($this->service->cancel($id)) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

}
