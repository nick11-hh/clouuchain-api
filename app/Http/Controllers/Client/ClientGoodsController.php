<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\ClientGoodsInfo;
use App\Http\Resources\Client\ClientGoodsList;
use App\Services\ApiResponseService;
use App\Services\Client\ClientGoodsService;
use Illuminate\Http\Request;

class ClientGoodsController extends Controller
{
    protected ClientGoodsService $service;

    public function __construct(ClientGoodsService $service)
    {
        $this->service = $service;
    }


    public function index()
    {
        $list = $this->service->index();
        return ClientGoodsList::collection($list)->additional(ApiResponseService::success());
    }


    public function show($id)
    {
        $data = $this->service->show($id);
        return ClientGoodsInfo::make($data)->additional(ApiResponseService::success());
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
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function publish(Request $request)
    {
        if ($this->service->publish($request->all())) {
            return ApiResponseService::successMessage('发起刊登请求成功，请留意后续日志变化');
        }
        return ApiResponseService::errorMessage('发起刊登请求失败');
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function publishBatch(Request $request)
    {
        if ($this->service->publishBatch($request->all())) {
            return ApiResponseService::successMessage('发起刊登请求成功，请留意后续日志变化');
        }
        return ApiResponseService::errorMessage('发起刊登请求失败');
    }

}
