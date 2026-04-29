<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PaymentSettingList;
use App\Services\Admin\PaymentSettingService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class PaymentSettingController extends Controller
{
    protected PaymentSettingService $service;

    public function __construct(PaymentSettingService $service)
    {
        $this->service = $service;
    }


    public function index()
    {
        $list = $this->service->index();
        return PaymentSettingList::collection($list)->additional(ApiResponseService::success());
    }


    public function show($id)
    {
        $data = $this->service->show($id);
        return PaymentSettingList::make($data)->additional(ApiResponseService::success());
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
     */
    public function updateStatus(Request $request)
    {
        if ($this->service->updateStatus($request->all())) {
            return ApiResponseService::successMessage('修改成功');
        }
        return ApiResponseService::errorMessage('修改失败');
    }

    /**
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function deletes()
    {
        if ($this->service->deletes()) {
            return ApiResponseService::successMessage('删除成功');
        }
        return ApiResponseService::errorMessage('删除失败');
    }
}
