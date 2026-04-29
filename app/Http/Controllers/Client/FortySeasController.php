<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use App\Services\Client\FortySeasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FortySeasController extends Controller
{
    public function __construct(private FortySeasService $fortySeasService)
    {

    }

    /**
     * 创建40Seas订单URL
     * @param Request $request
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     */
    public function checkout(Request $request): array
    {
        $validatedData = $this->validate($request, [
            'amount' => 'required|numeric',         // 金额
            'currency' => 'required|string',        // 货币类型
        ]);

        $result = $this->fortySeasService->checkout(
            (float)$validatedData['amount'],
            $validatedData['currency']
        );

        return ApiResponseService::success($result);
    }


    /**
     * 40Seas webhook
     * @param Request $request
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     */
    public function webhook(Request $request): array
    {
        $svixId = $request->header('svix-id',''); //webhook消息的唯一ID
        $svixTimestamp = $request->header('svix-timestamp',''); //消息发送时间
        $svixSignature = $request->header('svix-signature',''); //webhook消息的签名
        $body = $request->getContent();
        $headers = json_encode($request->header());

        // 记录完整的请求信息
        Log::info('40Seas Webhook Request', [
            'headers' => $request->header(),
            'all_params' => $request->all(),
            'raw_body' => $body
        ]);

        // 验证 webhook 签名
        if (!$this->fortySeasService->verifyWebhookSignature($svixId, $svixTimestamp, $svixSignature, $body)) {
            Log::warning('40Seas Webhook signature verification failed', [
                'svix_id' => $svixId,
                'svix_timestamp' => $svixTimestamp,
            ]);
            // 签名验证失败也返回200，防止重试
            return ApiResponseService::success('40Seas Signature verification failed');
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:payout.ready',
            'data' => 'required|array',
            'data.amount' => 'required|numeric',
            'data.balance' => 'nullable|numeric',
            'data.bankAccount' => 'nullable|string',
            'data.buyer' => 'required|string',
            'data.checkout' => 'nullable|string',
            'data.createdAt' => 'required|date',
            'data.currency' => 'required|string',
            'data.fees' => 'nullable|array',
            'data.fees.*.feeStatus' => 'required|string|in:done,pending',
            'data.fees.*.fixed' => 'nullable|numeric',
            'data.fees.*.percentage' => 'nullable|numeric',
            'data.fees.*.type' => 'required|string|in:buyer,immediate,net',
            'data.grossAmount' => 'nullable|numeric',
            'data.id' => 'required|string',
            'data.initiatedDate' => 'nullable|date',
            'data.invoice' => 'nullable|string',
            'data.metadata' => 'nullable|array',
            'data.netAmount' => 'nullable|numeric',
            'data.paidDate' => 'nullable|date',
            'data.paymentMethod' => 'required|string|in:card,ach,acss,becs,sepa_core,phone,wire,check',
            'data.seller' => 'required|string',
            'data.status' => 'required|string|in:pending,ready,in-progress,done,failed,canceled,scheduled,initiated',
            'data.tenantId' => 'required|string',
            'data.timeline' => 'nullable|array',
            'data.type' => 'required|string|in:manual,automatic,log',
            'data.updatedAt' => 'required|date'
        ]);

        if ($validator->fails()) {
            Log::error('40Seas Webhook validation failed: ' . json_encode($validator->errors()), [
                'svix_id' => $svixId,
                'payload' => $request->all()
            ]);

            return ApiResponseService::success('40Seas Signature verification failed');
        }

        $validatedData = $validator->validated();

        $result = $this->fortySeasService->webhook($validatedData['type'], $validatedData['data'], $svixId, $body, $headers);
        return ApiResponseService::success($result);
    }
}
