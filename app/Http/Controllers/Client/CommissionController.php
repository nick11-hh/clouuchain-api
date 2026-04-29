<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\CommissionList;
use App\Http\Resources\Client\InviteUserList;
use App\Http\Resources\Client\RechargeApplyList;
use App\Http\Resources\Client\WithdrawRecordList;
use App\Services\ApiResponseService;
use App\Services\Client\CommissionService;
use App\Services\Client\RechargeApplyService;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    protected CommissionService $service;

    public function __construct(CommissionService $service)
    {
        $this->service = $service;
    }


    public function withdrawList()
    {
        $list = $this->service->withdrawList();
        return WithdrawRecordList::collection($list)->additional(ApiResponseService::success());
    }


    public function commissionList()
    {
        $list = $this->service->commissionList();
        return CommissionList::collection($list)->additional(ApiResponseService::success());
    }

    public function inviteList()
    {
        $list = $this->service->inviteList();
        return InviteUserList::collection($list)->additional(ApiResponseService::success());
    }

    public function withdrawTypeList()
    {
        $data = $this->service->withdrawTypeList();
        return ApiResponseService::success($data);
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function withdrawApply(Request $request)
    {
        if ($this->service->withdrawApply($request->all())) {
            return ApiResponseService::successMessage('提交成功');
        }
        return ApiResponseService::errorMessage('提交失败');
    }


}
