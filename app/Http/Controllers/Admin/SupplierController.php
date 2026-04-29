<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SupplierList;
use App\Services\Admin\SupplierService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;


class SupplierController extends Controller
{
    protected SupplierService $service;

    public function __construct(SupplierService $service)
    {
        $this->service = $service;
    }


    public function index()
    {
        $list = $this->service->index();
        return SupplierList::collection($list)->additional(ApiResponseService::success());
    }

    public function getAllEnable()
    {
        return SupplierList::collection($this->service->getAllEnable())->additional(ApiResponseService::success());
    }

    public function show($id)
    {
        $data = $this->service->show($id);
        return SupplierList::make($data)->additional(ApiResponseService::success());
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

    /** 更新状态
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

    public function import(Request $request)
    {
        $result = $this->service->import($request->all());
        if ($result) {
            if ($result['success_count'] == 0) {
                return ApiResponseService::error([], '全部导入失败', 500);
            }
            return ApiResponseService::successMessage('导入成功'.$result['success_count'].'条数据'.', 导入失败'.$result['failure_count'].'条数据');
        }
        return ApiResponseService::error([], '全部导入失败', 500);
    }

}
