<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SupplierVisitResource;
use App\Services\Admin\SupplierVisitService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class SupplierVisitController extends Controller
{
    protected SupplierVisitService $service;

    public function __construct(SupplierVisitService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取供应商拜访记录列表
     */
    public function index()
    {
        $list = $this->service->index();
        return SupplierVisitResource::collection($list)->additional(ApiResponseService::success());
    }

    /**
     * 获取单个供应商拜访记录详情
     */
    public function show($id)
    {
        $data = $this->service->show($id);
        return SupplierVisitResource::make($data)->additional(ApiResponseService::success());
    }

    /**
     * 创建供应商拜访记录
     */
    public function store(Request $request)
    {
        if ($this->service->store($request->all())) {
            return ApiResponseService::successMessage('保存成功');
        }
        return ApiResponseService::errorMessage('保存失败');
    }

    /**
     * 更新供应商拜访记录
     */
    public function update($id, Request $request)
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::successMessage('保存成功');
        }
        return ApiResponseService::errorMessage('保存失败');
    }

    /**
     * 删除供应商拜访记录
     */
    public function destroy($id)
    {
        if ($this->service->destroy($id)) {
            return ApiResponseService::successMessage('删除成功');
        }
        return ApiResponseService::errorMessage('删除失败');
    }

    /**
     * 批量删除供应商拜访记录
     */
    public function batchDestroy(Request $request)
    {
        if ($this->service->batchDestroy($request->all())) {
            return ApiResponseService::successMessage('删除成功');
        }
        return ApiResponseService::errorMessage('删除失败');
    }
}