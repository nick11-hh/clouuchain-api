<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExchangeRateList;
use App\Services\Admin\ExchangeRateService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;

class ExchangeRateController extends Controller
{
    public ExchangeRateService $service;
    public function __construct(ExchangeRateService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return ExchangeRateList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function store(): JsonResponse|array
    {
        if($this->service->store()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function queryExchangeRate(): JsonResponse|array
    {
        $to = request()->get('to', 'CNY');
        $from = request()->get('from', 'USD');
        $res = $this->service->queryExchangeRate($to, $from);

        if($res) {
            return ApiResponseService::success($res);
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

    public function syncExchangeRate()
    {
        if($this->service->syncExchangeRate()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 获取支持的货币列表
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/26 15:54
     */
    public function getSupportCurrencyList()
    {
        $list = $this->service->getSupportCurrency();
        return ApiResponseService::success($list);
    }

    /**
     * 获取货币汇率列表
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/8 15:14
     */
    public function getRates()
    {
        return ApiResponseService::success($this->service->getRates());
    }
}
