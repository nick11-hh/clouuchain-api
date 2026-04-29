<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\PlatformProductList;
use App\Services\ApiResponseService;
use App\Services\Client\PlatformProductService;

class PlatformProductController extends Controller
{
    protected PlatformProductService $service;

    public function __construct(PlatformProductService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $list = $this->service->index();
        return PlatformProductList::collection($list)->additional(ApiResponseService::success());
    }


//    public function show($id)
//    {
//        $data = $this->service->show($id);
//        return ClientGoodsInfo::make($data)->additional(ApiResponseService::success());
//    }

    /**
     * @param $shopId
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function syncShopProduct($shopId)
    {
        $result = $this->service->syncShopProduct($shopId);
        if ($result) {
            return ApiResponseService::successMessage('同步成功');
        }
        return ApiResponseService::errorMessage('同步失败');
    }

    /**
     * @param $productId
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function syncOneProduct($productId)
    {
        $result = $this->service->syncOneProduct($productId);
        if ($result) {
            return ApiResponseService::successMessage('同步成功');
        }
        return ApiResponseService::errorMessage('同步失败');
    }

    /**
     * @param $productId
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function deleteProduct($productId)
    {
        $result = $this->service->deleteProduct($productId);
        if ($result) {
            return ApiResponseService::successMessage('删除成功');
        }
        return ApiResponseService::errorMessage('删除失败');
    }

    /**
     * 同步产品（异步）
     * @return array|\Illuminate\Http\JsonResponse
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/18 17:50
     */
    public function syncPlatformProductAll()
    {
        $result = $this->service->syncPlatformProductAll();
        return ApiResponseService::successMessage('同步产品任务已添加，请稍后刷新页面查看结果');
    }

}
