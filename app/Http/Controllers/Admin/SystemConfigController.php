<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\OpreateLogList;
use App\Services\Admin\SystemConfigService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemConfigController extends Controller
{
    protected SystemConfigService $service;

    public function __construct(SystemConfigService $service)
    {
        $this->service = $service;
    }

    public function getBaseConfig()
    {
        return ApiResponseService::success($this->service->getBaseConfig());
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function saveBaseConfig(Request $request)
    {
        if ($this->service->saveBaseConfig($request->all())) {
            return ApiResponseService::successMessage('保存成功');
        }
        return ApiResponseService::errorMessage('保存失败');
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function getMultipleConfig(Request $request)
    {
        return ApiResponseService::success($this->service->getMultipleConfig($request->all()));
    }

    /**
     * 获取操作日志列表
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/13 14:05
     */
    public function operateLogList(Request $request)
    {
        $list = $this->service->operateLogList($request->all());

        return OpreateLogList::collection($list)
            ->additional(ApiResponseService::success());
    }


    public function fulfillmentConfig()
    {
        return ApiResponseService::success($this->service->fulfillmentConfig());
    }

}
