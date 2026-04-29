<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\BalanceRechargeList;
use App\Http\Resources\Client\CreditCardRechargeList;
use App\Http\Resources\Client\RechargeApplyList;
use App\Models\CreditCardRechargeRecord;
use App\Services\ApiResponseService;
use App\Services\Client\RechargeApplyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RechargeApplyController extends Controller
{
    protected RechargeApplyService $service;

    public function __construct(RechargeApplyService $service)
    {
        $this->service = $service;
    }


    public function index()
    {
        $list = $this->service->index();
        return RechargeApplyList::collection($list)->additional(ApiResponseService::success());
    }


    public function show($id)
    {
        $data = $this->service->show($id);
        return RechargeApplyList::make($data)->additional(ApiResponseService::success());
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if ($this->service->store($request->all())) {
            return ApiResponseService::successMessage('提交成功');
        }
        return ApiResponseService::errorMessage('提交失败');
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

    public function balance(Request $request)
    {
        $list = $this->service->getBalanceRecord($request->all());
        return BalanceRechargeList::collection($list)->additional(ApiResponseService::success());
    }

    /**
     * 获取信用卡充值记录
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function creditCardRecharge(Request $request)
    {
        $list = $this->service->creditCardRecharge($request->all());

        return CreditCardRechargeList::collection($list)->additional(ApiResponseService::success());
    }
}
