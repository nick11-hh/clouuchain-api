<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\GoodsInfo;
use App\Http\Resources\Admin\GoodsList;
use App\Http\Resources\Admin\GoodsSkuDetailList;
use App\Http\Resources\Admin\GoodsSkuInfo;
use App\Services\Admin\GoodsService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsController extends Controller
{
    protected GoodsService $service;

    public function __construct(GoodsService $service)
    {
        $this->service = $service;
    }


    public function index()
    {
        $list = $this->service->index();
        return GoodsList::collection($list)->additional(ApiResponseService::success());
    }


    public function show($id)
    {
        $data = $this->service->show($id);
        return GoodsInfo::make($data)->additional(ApiResponseService::success());
    }

    public function skuShow($id)
    {
        $data = $this->service->skuShow($id);
        return GoodsSkuInfo::make($data)->additional(ApiResponseService::success());
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function skuList(Request $request)
    {
        return GoodsSkuDetailList::collection($this->service->skuList($request->all()))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if ($this->service->store($request->all())) {
            return ApiResponseService::successMessage('保存成功');
        }
        return ApiResponseService::errorMessage('保存失败');
    }

    /**
     * @param $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::successMessage('保存成功');
        }
        return ApiResponseService::errorMessage('保存失败');
    }

    /**
     * @param $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function skuUpdate($id, Request $request)
    {
        if ($this->service->skuUpdate($id, $request->all())) {
            return ApiResponseService::successMessage('保存成功');
        }
        return ApiResponseService::errorMessage('保存失败');
    }

    /** 更新状态
     * @param $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request)
    {
        if ($this->service->updateStatus($request->all())) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    /**
     * 中国热卖商品状态更新
     * @param Request $request
     * @return array|JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update1688Status(Request $request)
    {
        if ($this->service->update1688Status($request->all())) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    /** 商品审核
     * @param $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function auditGoods(Request $request)
    {
        $res = $this->service->auditGoods($request->all());
        if ($res['status']) {
            return ApiResponseService::successMessage($res['msg']);
        }
        return ApiResponseService::errorMessage($res['msg']);
    }
    /** 商品提交审核
     * @param $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function commitAuditGoods(Request $request)
    {
        $res=$this->service->commitAuditGoods($request->all());
        if ($res['status']) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage($res['msg']);
    }
    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function updateHot(Request $request)
    {
        $result=$this->service->updateHot($request->all());
        if ($result['status']) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage($result['msg']);
    }

    public function markAsSelfOperated(Request $request)
    {
        $result=$this->service->markAsSelfOperated($request->all());
        if ($result['status']) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage($result['msg']);
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function update1688(Request $request)
    {
        $result=$this->service->update1688($request->all());
        if ($result['status']) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage($result['msg']);
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function deletes(Request $request)
    {
        if ($this->service->deletes($request->all())) {
            return ApiResponseService::successMessage('删除成功');
        }
        return ApiResponseService::errorMessage('删除失败');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function skuQuotationList(Request $request)
    {
        return GoodsSkuDetailList::collection($this->service->skuQuotationList($request->all()))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function spuQuotationList(Request $request)
    {
        return GoodsSkuDetailList::collection($this->service->spuQuotationList($request->all()))
            ->additional(ApiResponseService::success());
    }

    public function batchUpdateDeclaration()
    {
        if($this->service->batchUpdateDeclaration()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 导入产品
     * @return array|JsonResponse
     * @throws \Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/13 16:07
     */
    public function import()
    {
        $this->service->import();

        return ApiResponseService::success();
    }

    public function pushToMabang($id)
    {

        try {
            $this->service->pushToMabang($id);

            return ApiResponseService::success(message: '已加入队列，请在日志列表查看推送状态');
        } catch (\Exception $e) {

            return ApiResponseService::error(message: $e->getMessage());
        }

    }

    public function skuPushToMabang($id)
    {

        try {
            $this->service->skuPushToMabang($id);

            return ApiResponseService::success(message: '已加入队列，请在日志列表查看推送状态');
        } catch (\Exception $e) {

            return ApiResponseService::error(message: $e->getMessage());
        }

    }

    public function calculateQuotation()
    {

        try {

            $quotation = $this->service->calculateQuotation();

            return ApiResponseService::success(['quotation' => $quotation]);

        } catch (Exception $e) {

            return ApiResponseService::error(message: '产品报价计算失败');
        }

    }

}
