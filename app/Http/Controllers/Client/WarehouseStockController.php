<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\WarehouseStockList;
use App\Services\Client\WarehouseStockService;
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


}
