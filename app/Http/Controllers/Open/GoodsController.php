<?php
namespace App\Http\Controllers\Open;

use App\Http\Controllers\Controller;
use App\Services\Open\GoodsService;
use App\Services\ApiResponseService;
use App\Http\Resources\Open\GoodsList;
use App\Http\Resources\Open\GoodsInfo;
use App\Http\Resources\Open\GoodsCategoryTree;

class GoodsController extends Controller
{
    /**
     * @var GoodsService
     */
    private GoodsService $service;

    /**
     * @param GoodsService $authService
     */
    public function __construct(GoodsService $authService) {
        $this->service = $authService;
    }

    /**
     * 产品分类列表
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getCategoryList()
    {
        $list = $this->service->getCategoryList();
        return GoodsCategoryTree::collection($list)->additional(ApiResponseService::success());
    }

    /**
     * 热销产品列表
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getGoodsList()
    {
        $list = $this->service->getGoodsList();
        return GoodsList::collection($list)->additional(ApiResponseService::success());
    }

    /**
     * 热销产品详情
     * @param $spu
     * @return GoodsInfo
     */
    public function getGoodsDetailBySpu()
    {
        $data = $this->service->getGoodsDetail();
        return GoodsInfo::make($data)->additional(ApiResponseService::success());
    }
}
