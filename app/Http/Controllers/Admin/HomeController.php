<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use App\Services\Admin\HomeService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    protected $service;

    public function __construct(HomeService $service)
    {
        $this->service = $service;
    }

    /**
     * @return array
     */
    public function todoData()
    {
        $data = $this->service->todoData();
        return ApiResponseService::success($data);
    }

    /**
     * @return array
     */
    public function statisticsData()
    {
        $data = $this->service->statisticsData();
        return ApiResponseService::success($data);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function customStatistics(Request $request)
    {
        $data = $this->service->customStatistics($request->all());
        return ApiResponseService::success($data);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function rechargeStatistics(Request $request)
    {
        $data = $this->service->rechargeStatistics($request->all());
        return ApiResponseService::success($data);
    }

    /***
     * @param Request $request
     * @return array
     */
    public function purchaseStatistics(Request $request)
    {
        $data = $this->service->purchaseStatistics($request->all());
        return ApiResponseService::success($data);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function hotSaleGoods(Request $request)
    {
        $data = $this->service->hotSaleGoods($request->all());
        return ApiResponseService::success($data);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function applyExpressFail(Request $request)
    {
        $data = $this->service->applyExpressFail($request->all());
        return ApiResponseService::success($data);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function getAdminUserInfo()
    {
        $data = $this->service->getAdminUserInfo();
        return ApiResponseService::success($data);
    }

    /** 修改用户密码
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function updateAdminUser(Request $request)
    {
        if ($this->service->updateAdminUser($request->all())) {
            return ApiResponseService::successMessage('更改成功');
        }
        return ApiResponseService::errorMessage('更改失败');
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

}
