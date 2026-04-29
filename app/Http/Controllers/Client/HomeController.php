<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use App\Services\Client\HomeService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    protected $service;

    public function __construct(HomeService $service)
    {
        $this->service = $service;
    }

    /** 订单数据统计
     * @return array
     */
    public function orderStatistics()
    {
        $data = $this->service->orderStatistics();
        return ApiResponseService::success($data);
    }

    /** 产品数据统计
     * @return array
     */
    public function productStatistics()
    {
        $data = $this->service->productStatistics();
        return ApiResponseService::success($data);
    }

    /** 收入支出数据统计
     * @param Request $request
     * @return array
     */
    public function incomeStatistics(Request $request)
    {
        $data = $this->service->incomeStatistics($request->all());
        return ApiResponseService::success($data);
    }

    /** 订单量
     * @param Request $request
     * @return array
     */
    public function orderTotalStatistics(Request $request)
    {
        $data = $this->service->orderTotalStatistics($request->all());
        return ApiResponseService::success($data);
    }

    /** 获取客户信息
     * @return array
     */
    public function getCustomInfo()
    {
        $data = $this->service->getCustomInfo();
        return ApiResponseService::success($data);
    }

    /** 修改客户应用
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function updateCustom(Request $request)
    {
        if ($this->service->updateCustom($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    /** 修改客户邮箱
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function updateEmail(Request $request)
    {
        if ($this->service->updateEmail($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    /** 修改用户头像
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function updateAvatar(Request $request)
    {
        if ($this->service->updateAvatar($request->all())) {
            return ApiResponseService::successMessage('修改头像成功');
        }
        return ApiResponseService::errorMessage('修改头像失败');
    }

    /** 修改用户密码
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function updatePassword(Request $request)
    {
        if ($this->service->updatePassword($request->all())) {
            return ApiResponseService::successMessage('更改成功');
        }
        return ApiResponseService::errorMessage('更改失败');
    }

    public function getCustomConfig()
    {
        $data = $this->service->getCustomConfig();
        return ApiResponseService::success($data);
    }

    /**
     * 保存用户自定义配置
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function saveCustomConfig(Request $request)
    {
        if ($this->service->saveCustomConfig($request->all())) {
            return ApiResponseService::successMessage('更改成功');
        }
        return ApiResponseService::errorMessage('更改失败');
    }

    /** 今日统计
     * @return array
     */
    public function todayStatistics()
    {
        $data = $this->service->todayStatistics();
        return ApiResponseService::success($data);
    }

    /**
     * 营收数据统计
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/16 11:48
     */
    public function revenueDataStatistics()
    {
        $data = $this->service->revenueDataStatistics();
        return ApiResponseService::success($data);
    }

    /**
     * 世界国家
     * @return array
     */
    public function worldCountries()
    {
        return ApiResponseService::success($this->service->worldCountries());
    }

}
