<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\GoodsInfo;
use App\Http\Resources\Client\PlatformProductList;
use App\Http\Resources\Client\PlatformProductQuoteDetail;
use App\Models\SystemConfig;
use App\Services\ApiResponseService;
use App\Services\Base\SystemConfigService;
use App\Services\Client\ProductQuoteService;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use Illuminate\Http\Request;

class ProductQuoteController extends Controller
{

    protected ProductQuoteService $service;

    public function __construct(ProductQuoteService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $list = $this->service->index();
        return PlatformProductList::collection($list)->additional(ApiResponseService::success());
    }

    public function show($id)
    {
        return PlatformProductList::make($this->service->show($id))->additional(ApiResponseService::success());
    }

    public function count()
    {
        $res = ApiResponseService::success($this->service->count());
        $res['goods_one_price'] =  SystemConfigService::getConfigValue(SystemConfig::ORDER_ONE_PRICE);
        return $res;
    }

    public function getProductList(Request $request)
    {
        return GoodsInfo::collection($this->service->getProductList($request->all()))
            ->additional(ApiResponseService::success());
    }

    public function saveQuote($id, Request $request)
    {
        if ($this->service->saveQuote($id, $request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function saveLogisticsChannel($id, Request $request)
    {
        if ($this->service->saveLogisticsChannel($id, $request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function requestQuote(Request $request)
    {
        if ($this->service->requestQuote($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function batchRequestQuote(Request $request)
    {
        if ($this->service->batchRequestQuote($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function getSkuQuotePrice(Request $request)
    {
        if ($result = $this->service->getSkuQuotePrice($request->all())) {
            return ApiResponseService::success($result);
        }
        return ApiResponseService::error();
    }

    /**
     * 接受报价
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/15 11:52
     */
    public function confirmQuote(Request $request)
    {
        if ($this->service->confirmQuote($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 获取报价详情
     * @return PlatformProductQuoteDetail|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/2/13 16:41
     */
    public function quoteDetail($id)
    {
        return PlatformProductQuoteDetail::make($this->service->getQuoteDetail($id))
            ->additional(ApiResponseService::success());
    }


}
