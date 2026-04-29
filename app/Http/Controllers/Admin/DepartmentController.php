<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminList;
use App\Http\Resources\Admin\DepartmentList;
use App\Http\Resources\Admin\DepartmentTree;
use App\Services\Admin\DepartmentService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    protected DepartmentService $service;

    public function __construct(DepartmentService $service)
    {
        $this->service = $service;
    }


    public function index()
    {
        $list = $this->service->index();
        return DepartmentList::collection($list)->additional(ApiResponseService::success());
    }

    public function tree()
    {
        $list = $this->service->tree();
        return DepartmentTree::collection($list)->additional(ApiResponseService::success());
    }

    public function show($id)
    {
        $data = $this->service->show($id);
        return DepartmentList::make($data)->additional(ApiResponseService::success());
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
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    /** 更新状态
     * @param $id
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
     */
    public function deletes(Request $request)
    {
        if ($this->service->deletes($request->all())) {
            return ApiResponseService::successMessage('删除成功');
        }
        return ApiResponseService::errorMessage('删除失败');
    }

    public function getStaffList($id, Request $request)
    {
        $data = $this->service->getStaffList($id, $request->all());
        return AdminList::collection($data)->additional(ApiResponseService::success());
    }

    public function assignStaff($id, Request $request)
    {
        if ($this->service->assignStaff($id, $request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    public function removeStaff($id, Request $request)
    {
        if ($this->service->removeStaff($id, $request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    public function updateStaffMain($id, Request $request)
    {
        if ($this->service->updateStaffMain($id, $request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

}
