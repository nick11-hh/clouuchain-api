<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\CustomAddressInfo;
use App\Http\Resources\Client\CustomAddressList;
use App\Services\ApiResponseService;
use App\Services\Client\CustomAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 客户地址管理
 * Class CustomAddressController
 * @package App\Http\Controllers\Client
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/8/29 19:41
 */
class CustomAddressController extends Controller
{
    public CustomAddressService $service;

    /**
     * @param CustomAddressService $service
     */
    public function __construct(CustomAddressService $service)
    {
        $this->service = $service;
    }

    /**
     * 首页
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/8/29 20:50
     */
    public function index()
    {
        $list = $this->service->index();

        return CustomAddressList::collection($list)
            ->additional(ApiResponseService::success());
    }

    /**
     * 详情
     * @param $id
     * @return CustomAddressInfo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/8/29 20:50
     */
    public function show($id)
    {
        $data = $this->service->show($id);

        return CustomAddressInfo::make($data)
            ->additional(ApiResponseService::success());
    }

    /**
     * 新增
     * @param Request $request
     * @return array|JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/8/29 20:50
     */
    public function store(Request $request)
    {
        if ($this->service->store($request->all())) {
            return ApiResponseService::successMessage('新增地址成功');
        }
        return ApiResponseService::errorMessage('新增地址失败');
    }

    /**
     * 更新
     * @param int $id
     * @param Request $request
     * @return array|JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/8/29 20:50
     */
    public function update(int $id, Request $request)
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::successMessage('修改地址成功');
        }
        return ApiResponseService::errorMessage('修改地址失败');
    }

    /**
     * 删除
     * @param Request $request
     * @return array|JsonResponse
     * @throws \Throwable
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/8/29 20:50
     */
    public function deletes(Request $request)
    {
        if ($this->service->deletes($request->all())) {
            return ApiResponseService::successMessage('删除成功');
        }

        return ApiResponseService::errorMessage('删除失败');
    }

    /**
     * 设置默认地址
     * @param int $id
     * @return array|JsonResponse
     * @throws \Throwable
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/8/29 20:50
     */
    public function setDefault(int $id)
    {
        if ($this->service->setDefault($id)) {
            return ApiResponseService::successMessage('设置成功');
        }

        return ApiResponseService::errorMessage('设置失败');
    }
}
