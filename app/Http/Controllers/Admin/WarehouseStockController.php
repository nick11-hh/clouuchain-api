<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CustomerStockGoodsList;
use App\Http\Resources\Admin\WarehouseStockChangeList;
use App\Http\Resources\Admin\WarehouseStockInfo;
use App\Http\Resources\Admin\WarehouseStockItemsList;
use App\Http\Resources\Admin\WarehouseStockList;
use App\Services\Admin\WarehouseStockService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class WarehouseStockController extends Controller
{
    public WarehouseStockService $service;

    public function __construct(WarehouseStockService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return WarehouseStockList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function show($id)
    {
        return WarehouseStockInfo::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    public function stockItems(Request $request)
    {
        return WarehouseStockItemsList::collection($this->service->stockItems($request->all()))
            ->additional(ApiResponseService::success());
    }

    public function records()
    {
        return WarehouseStockChangeList::collection($this->service->records())
            ->additional(ApiResponseService::success());
    }

    public function getCustomerStock($id, Request $request)
    {
        return CustomerStockGoodsList::collection($this->service->getCustomerStock($id, $request->all()))->additional(ApiResponseService::success());
    }


}
