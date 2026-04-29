<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\RechargeApplyList;
use App\Http\Resources\Admin\OnlineRechargeList;
use App\Services\ApiResponseService;
use App\Services\Admin\RechargeApplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    /** 审核成功
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

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function quickRecharge(Request $request)
    {
        if ($this->service->quickRecharge($request->all())) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    /**
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     */
    public function revocation(Request $request)
    {
        if ($this->service->revocation($request->all())) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    /**
     * 线上充值记录列表
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/5 16:45
     */
    public function onlineRecharge(Request $request)
    {
        $list = $this->service->getOnlineRechargeList($request->all());
        return OnlineRechargeList::collection($list)->additional(ApiResponseService::success());
    }

    /**
     * 提交核账
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/7 14:37
     */
    public function submitCheck(Request $request)
    {
        if ($this->service->submitCheck($request->all())) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    /**
     * 导出
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/8 16:04
     */
    public function export(Request $request)
    {
        if ($this->service->export($request->all())) {
            return ApiResponseService::successMessage('导出任务添加成功，请到顶部订单下载管理中查看进度和下载');
        }
        return ApiResponseService::errorMessage('导出失败');
    }

    public function chargePayMethodList()
    {
        return ApiResponseService::success($this->service->chargePayMethodList());
    }

    public function addChargePayMethod(Request $request)
    {
        if ($this->service->addChargePayMethod($request->all())) {
            return ApiResponseService::successMessage('保存成功');
        }
        return ApiResponseService::errorMessage('保存失败');
    }

    public function updateChargePayMethod($id, Request $request)
    {
        if ($this->service->updateChargePayMethod($id, $request->all())) {
            return ApiResponseService::successMessage('更新成功');
        }
        return ApiResponseService::errorMessage('更新失败');
    }

    public function updatePayMethodStatus($id, Request $request)
    {
        if ($this->service->updatePayMethodStatus($id, $request->all())) {
            return ApiResponseService::successMessage('更新成功');
        }
        return ApiResponseService::errorMessage('更新失败');
    }

    public function deleteChargePayMethod($id)
    {
        if ($this->service->deleteChargePayMethod($id)) {
            return ApiResponseService::successMessage('删除成功');
        }
        return ApiResponseService::errorMessage('删除失败');
    }

}
