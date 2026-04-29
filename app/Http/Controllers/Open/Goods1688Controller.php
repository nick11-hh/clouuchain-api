<?php
namespace App\Http\Controllers\Open;

use App\Http\Controllers\Controller;
use App\Services\Open\Goods1688Service;
use App\Services\ApiResponseService;
use App\Http\Resources\Open\Goods1688List;
use App\Http\Resources\Open\Goods1688Info;
use App\Http\Resources\Open\GoodsCategoryTree;

class Goods1688Controller extends Controller
{
    /**
     * @var Goods1688Service
     */
    private Goods1688Service $service;

    /**
     * @param Goods1688Service $authService
     */
    public function __construct(Goods1688Service $authService) {
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
        return Goods1688List::collection($list)->additional(ApiResponseService::success());
    }

    /**
     * 热销产品详情
     * @param $spu
     * @return Goods1688Info
     */
    public function getGoodsDetailBySpu()
    {
        $data = $this->service->getGoodsDetail();
        return Goods1688Info::make($data)->additional(ApiResponseService::success());
    }
}
