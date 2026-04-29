<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\AdminGoodsCategoryTree;
use App\Http\Resources\Client\AdminGoodsInfo;
use App\Http\Resources\Client\AdminGoodsList;
use App\Models\Goods;
use App\Services\ApiResponseService;
use App\Services\Client\AdminGoodsService;

class AdminGoodsController extends Controller
{
    private AdminGoodsService $service;

    public function __construct(AdminGoodsService $authService)
    {
        $this->service = $authService;
    }

    /** 热销产品分类
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getCategoryList()
    {
        $data = $this->service->getCategoryList();
        return AdminGoodsCategoryTree::collection($data)->additional(ApiResponseService::success());
    }

    /** 热销产品列表
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getGoodsList()
    {
        $list = $this->service->getGoodsList();
        return AdminGoodsList::collection($list)->additional(ApiResponseService::success());
    }

    /** 热销产品详情
     * @param $id
     * @return AdminGoodsInfo
     */
    public function getGoodsDetail($id)
    {
        $data = $this->service->getGoodsDetail($id);
        return AdminGoodsInfo::make($data)->additional(ApiResponseService::success());
    }

    /** 将热销产品加入到我的产品库
     * @param $id
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function addToClientGoods($id)
    {
        $goods = Goods::query()->with(['category', 'skus'])->findOrFail($id);
        if ($this->service->addToClientGoods($goods)) {
            return ApiResponseService::successMessage('添加成功');
        }
        return ApiResponseService::errorMessage('添加失败');
    }

    /**
     * 根据SPU查询热销产品详情
     * @param $spu
     * @return AdminGoodsInfo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/27 16:37
     */
    public function getGoodsDetailBySpu($spu)
    {
        $data = $this->service->getGoodsDetail(0, $spu);
        return AdminGoodsInfo::make($data)->additional(ApiResponseService::success());
    }

}
