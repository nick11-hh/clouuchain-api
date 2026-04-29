<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\Admin\CreditCardRechargeList;
use App\Http\Resources\Admin\OnlineRechargeList;
use App\Services\Admin\CreditCardRechargeRecordService;
use App\Services\Admin\RechargeApplyService;
use App\Services\ApiResponseService;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class CreditCardRechargeRecordController extends Controller
{
    use ValidatesRequests;

    public function __construct(private CreditCardRechargeRecordService $creditCardRechargeRecordService, private RechargeApplyService $rechargeApplyService)
    {

    }

    /**
     * 列表
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function list(Request $request)
    {
        $list = $this->creditCardRechargeRecordService->list($request->all());
        return CreditCardRechargeList::collection($list)->additional(ApiResponseService::success());
    }

    /**
     * 核销
     * @param Request $request
     * @return array|JsonResponse
     * @throws ValidationException
     * @throws \App\Exceptions\AccidentException
     */
    public function submitCheck(Request $request)
    {
        $requestData = $this->validate($request, [
            'id' => 'required|int',
            'check_admin_id' => 'required|int',
            'check_desc' => 'required|string|max:200',
            'check_images' => 'required|array',
        ]);
        $this->creditCardRechargeRecordService->submitCheck($requestData);
        return ApiResponseService::successMessage('操作成功');
    }

}
