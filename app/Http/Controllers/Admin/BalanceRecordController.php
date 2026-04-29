<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\BalanceRecordList;
use App\Services\Admin\BalanceRecordService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class BalanceRecordController extends Controller
{
    protected BalanceRecordService $service;

    public function __construct(BalanceRecordService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $list = $this->service->index();
        return BalanceRecordList::collection($list)->additional(ApiResponseService::success());
    }

    public function sourceTypeList()
    {
        $data = $this->service->model::sourceList();

        $newData = [];
        foreach ($data as $key => $value) {

            $arr['value'] = $key;
            $arr['label'] = $value;
            $newData[] = $arr;
        }

        return ApiResponseService::success($newData);
    }

    public function show($id)
    {
        $data = $this->service->show($id);
        return BalanceRecordList::make($data)->additional(ApiResponseService::success());
    }

    /**
     * 导出
     * @return array|\Illuminate\Http\JsonResponse
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/8 16:04
     */
    public function export()
    {
        if ($this->service->export()) {
            return ApiResponseService::successMessage('导出任务添加成功,请到顶部订单下载管理中查看进度和下载');
        }
        return ApiResponseService::errorMessage('导出失败');
    }

    /**
     * 手动操作余额
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/18 18:37
     */
    public function manuallyOperateBalance(Request $request)
    {
        if ($this->service->manuallyOperateBalance($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 批量添加明细
     */
    public function batchAddBreakdown(Request $request)
    {
        if ($this->service->batchAddBreakdown($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

}
