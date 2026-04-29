<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use App\Http\Resources\Admin\AfterSalesWorkOrderList;
use App\Services\Admin\AfterSalesWorkOrderService;
use Illuminate\Http\Request;

/**
 * Class AfterSalesWorkOrderController
 * @package App\Http\Controllers\Admin
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/9/6 19:29
 */
class AfterSalesWorkOrderController extends Controller
{
    public AfterSalesWorkOrderService $service;

    /**
     * 初始化
     * @param AfterSalesWorkOrderService $service
     */
    public function __construct(AfterSalesWorkOrderService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取列表
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/6 19:33
     */
    public function index()
    {
        $list = $this->service->index();
        return AfterSalesWorkOrderList::collection($list)->additional(ApiResponseService::success());
    }

    /**
     * 更新
     * @param $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception
     * @throws \Illuminate\Validation\ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/6 19:34
     */
    public function update($id, Request $request)
    {
        if (($this->service->update($id, $request->all()))) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }

    /**
     * 获取工单类型
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/6 19:35
     */
    public function getTypeList()
    {
        $list = $this->service->getTypeList();
        return ApiResponseService::success($list);
    }
}
