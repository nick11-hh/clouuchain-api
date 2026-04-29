<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CommissionWithdrawList;
use App\Http\Resources\Admin\CommissionWithdrawInfo;
use App\Services\Admin\CommissionWithdrawService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class CommissionWithdrawController extends Controller
{
    protected CommissionWithdrawService $service;

    public function __construct(CommissionWithdrawService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $list = $this->service->index();
        return CommissionWithdrawList::collection($list)->additional(ApiResponseService::success());
    }


    public function show($id)
    {
        $data = $this->service->show($id);
        return CommissionWithdrawInfo::make($data)->additional(ApiResponseService::success());
    }


    /** 审核
     * @param $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function audit($id, Request $request)
    {
        if ($this->service->audit($id, $request->all())) {
            return ApiResponseService::successMessage('审核成功');
        }
        return ApiResponseService::errorMessage('审核失败');
    }

}
