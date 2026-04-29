<?php

namespace App\Http\Controllers\Admin;

use App\Services\Admin\CreditCardService;
use App\Services\ApiResponseService;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CreditCardController extends Controller
{
    use ValidatesRequests;
    public function __construct(CreditCardService $creditCardService)
    {
        $this->creditCardService = $creditCardService;
    }

    /**
     * 信用卡配置信息
     * @return JsonResponse|array
     */
    public function all(): JsonResponse|array
    {
        $result =  $this->creditCardService->all();
        return ApiResponseService::successMessage('获取成功',$result);
    }

    /**
     * 设置信用卡状态
     * @param Request $request
     * @return array|JsonResponse
     * @throws \App\Exceptions\AccidentException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function status(Request $request): JsonResponse|array
    {
        $validate = $this->validate($request, [
            'id' => 'required|int',
            'status' => 'required|int',
        ]);
        $this->creditCardService->status($validate['id'],$validate['status']);
        return ApiResponseService::successMessage('操作成功');
    }

    /**
     * 修改信用卡配置
     * @param Request $request
     * @return array|JsonResponse
     * @throws \App\Exceptions\AccidentException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request): JsonResponse|array
    {
        $validate = $this->validate($request, [
            'id' => 'required|integer',
            'client_id' => 'required|string|max:255',
            'client_secret' => 'required|string|max:255',
            'webhook_secret' => 'required|string|max:255',
            'minimum_payment' => 'nullable|numeric|min:0',
            'service_charge_rate' => 'nullable|numeric|min:0|max:100',
            'service_charge_amount' => 'nullable|numeric|min:0',
        ]);
        $this->creditCardService->update($validate);
        return ApiResponseService::successMessage('操作成功');
    }
}
