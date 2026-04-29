<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use App\Services\Client\ExchangeRateService;

class ExchangeRateController extends Controller
{
    public ExchangeRateService $service;
    public function __construct(ExchangeRateService $service)
    {
        $this->service = $service;
    }

    public function getRates()
    {
        return ApiResponseService::success($this->service->getRates());
    }
}
