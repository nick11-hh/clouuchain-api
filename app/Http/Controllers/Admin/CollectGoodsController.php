<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CollectGoodsInfo;
use App\Http\Resources\Admin\CollectGoodsList;
use App\Services\Admin\CollectGoodsService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class CollectGoodsController extends Controller
{
    protected CollectGoodsService $service;

    public function __construct(CollectGoodsService $service)
    {
        $this->service = $service;
    }


    public function index()
    {
        $list = $this->service->index();
        return CollectGoodsList::collection($list)->additional(ApiResponseService::success());
    }


    public function show($id)
    {
        $data = $this->service->show($id);
        return CollectGoodsInfo::make($data)->additional(ApiResponseService::success());
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function collect(Request $request)
    {
        if ($data = $this->service->collect($request->all())) {
            return ApiResponseService::success($data);
        }
        return ApiResponseService::errorMessage('采集失败');
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
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function claim(Request $request)
    {
        if ($this->service->claim($request->all())) {
            return ApiResponseService::successMessage('认领成功');
        }
        return ApiResponseService::errorMessage('认领失败');
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


    public function getDetail(Request $request)
    {
        return ApiResponseService::success($this->service->getDetail($request->all()));
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function collectAndClaim(Request $request)
    {
        if ($this->service->collectAndClaim($request->all())) {
            return ApiResponseService::successMessage('采集成功');
        }
        return ApiResponseService::errorMessage('采集失败');
    }

}
