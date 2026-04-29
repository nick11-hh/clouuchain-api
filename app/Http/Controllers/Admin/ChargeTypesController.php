<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\HasBatchOperate;
use App\Http\Resources\ChargeTypeList;
use App\Services\Admin\ChargeTypesService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ChargeTypesController extends Controller
{
    use HasBatchOperate;

    protected ChargeTypesService $service;

    public function __construct(ChargeTypesService $service)
    {
        $this->service = $service;
    }

    /**
     * @param Request $request
     */
    public function index(Request $request)
    {
        return ChargeTypeList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * 获取单个数据
     * @param int $id
     * @return ChargeTypeList
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/21 10:42
     */
    public function show(int $id)
    {
        return ChargeTypeList::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if ($this->service->store()) {
            return ApiResponseService::successMessage('保存成功');
        }
        return ApiResponseService::errorMessage('保存失败');
    }

    public function update($id, Request $request)
    {
        if ($this->service->update($id)) {
            return ApiResponseService::successMessage();
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

}
