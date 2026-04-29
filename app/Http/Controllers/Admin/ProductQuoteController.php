<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\GoodsInfo;
use App\Http\Resources\Client\PlatformProductList;
use App\Http\Resources\Client\PlatformProductQuoteDetail;
use App\Models\SystemConfig;
use App\Services\ApiResponseService;
use App\Services\Admin\ProductQuoteService;
use App\Services\Client\ProductQuoteService as ClientProductQuoteService;
use App\Services\Base\SystemConfigService;
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
        $res['goods_one_price'] = (bool) SystemConfigService::getConfigValue(SystemConfig::ORDER_ONE_PRICE);
        return $res;
//        return ApiResponseService::success($this->service->count());
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

    public function reviewSuccess($id, Request $request)
    {
        if ($this->service->reviewSuccess($id, $request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function reviewReject(Request $request)
    {
        if ($this->service->reviewReject($request->all())) {
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

    public function getSkuQuotePrice(Request $request)
    {
        if ($result = $this->service->getSkuQuotePrice($request->all())) {
            return ApiResponseService::success($result);
        }
        return ApiResponseService::error();
    }

    /**
     * 提交报价
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/2/14 15:25
     */
    public function submitQuote(Request $request)
    {
        if ($result = $this->service->submitQuote($request->all())) {
            return ApiResponseService::success($result);
        }
        return ApiResponseService::error();
    }

    /**
     * 关联本地商品SKU(更新临时表)
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/2/14 0:35
     */
    public function associationGoodsSku(Request $request)
    {
        if ($result = $this->service->associationGoodsSku($request->all())) {
            return ApiResponseService::success($result);
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
        return PlatformProductQuoteDetail::make((new ClientProductQuoteService())->getQuoteDetail($id))
            ->additional(ApiResponseService::success());
    }

    public function customerQuote($id)
    {
        return ApiResponseService::success($this->service->customerQuote($id));
    }

    public function saveCustomerQuote($id, Request $request)
    {
        if ($result = $this->service->saveCustomerQuote($id, $request->all())) {
            return ApiResponseService::success($result);
        }
        return ApiResponseService::error();
    }

}
