<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\GoodsSupplierList;
use App\Services\Admin\GoodsSupplierService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class GoodsSupplierController extends Controller
{
    protected GoodsSupplierService $service;

    public function __construct(GoodsSupplierService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $list = $this->service->index();
        return GoodsSupplierList::collection($list)->additional(ApiResponseService::success());
    }

    public function show($id)
    {
        $data = $this->service->show($id);
        return GoodsSupplierList::make($data)->additional(ApiResponseService::success());
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
     * @throws \Exception
     */
    public function deletes(Request $request)
    {
        if ($this->service->deletes($request->all())) {
            return ApiResponseService::successMessage('删除成功');
        }
        return ApiResponseService::errorMessage('删除失败');
    }

    public function getDetailBySku()
    {
        if ($data = $this->service->getDetailBySku()) {
            return GoodsSupplierList::make($data)->additional(ApiResponseService::success());
        }
        return ApiResponseService::errorMessage('未查询到供应商关联的1688商品链接');
    }

}
