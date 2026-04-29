<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\BalanceRecordList;
use App\Http\Resources\Client\PaymentSettingList;
use App\Http\Resources\Client\TransferRecordList;
use App\Models\BalanceRecharge;
use App\Models\BalanceRecord;
use App\Models\Landlord\Tenant;
use App\Models\PaypalPayment;
use App\Services\ApiResponseService;
use App\Services\Base\PayPalService;
use App\Services\Client\BalanceService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exceptions\AccidentException;

class BalanceController extends Controller
{
    protected BalanceService $service;

    public function __construct(BalanceService $service)
    {
        $this->service = $service;
    }


    public function balance()
    {
        return ApiResponseService::success($this->service->balance());
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function paymentList()
    {
        $list = $this->service->getPaymentList();
        return PaymentSettingList::collection($list)->additional(ApiResponseService::success());
    }


    public function records()
    {
        $list = $this->service->records();
        return BalanceRecordList::collection($list)->additional(ApiResponseService::success());
    }

    public function transferRecords(Request $request)
    {
        $list = $this->service->transferRecords($request->all());
        return TransferRecordList::collection($list)->additional(ApiResponseService::success());
    }

    /**
     * 支付
     * @return array
     * @throws Exception
     */
    public function payment(Request $request)
    {
        $data = $this->service->payment($request->all());

        return ApiResponseService::success($data);
    }

    /**
     * 支付回调
     * @param Request $request
     * @throws Exception
     */
    public function paypalCallBack(Request $request)
    {
        info('Paypal 回调', [$request]);
        $request->validate(['host' => 'required']);

        $status = 'error';
        $host = urldecode($request['host']);
        try {
            $payerID = $request['PayerID'];
            $payID = $request['paymentId'];

            $record = BalanceRecharge::query()->where('out_trade_no', $payID)->first();
            if (empty($record)) {
                throw new AccidentException('充值记录不存在');
            }

            if ($record->status == 1) return $this->redirectWeb($host, 'success');

            $paypal = PaypalPayment::query()->first();
            $testMode = (boolean)($paypal->sandbox ?? 0);

            $service = new PayPalService();
            $service->withDBConfig($testMode);
            $data = $service->completePay($payerID, $payID);
            info('Paypal 支付结果', [$data]);
            if (empty($data)) {
                throw new AccidentException('Paypal 支付失败');
            }

            //更新客户余额
            $this->paymentSuccess($record);

            $status = 'success';
        } catch (Exception $e) {
            info('Paypal 支付结果出现异常', [$e->getMessage(), $e->getLine(), $e->getFile()]);
        }

        return $this->redirectWeb($host, $status);
    }

    /**  支付成功
     * @param $record
     */
    public function paymentSuccess($record)
    {
        return DB::transaction(function () use ($record) {
            $record->status = BalanceRecharge::STATUS_SUCCESS;
            $record->notify_time = now();
            $record->save();

            $balanceService = new \App\Services\Base\BalanceService($record->custom_id);
            $balanceService->setRelationId($record->id)->increase($record->recharge_amount, BalanceRecord::SOURCE_PAYPAL_RECHARGE, $record->out_trade_no);
            return true;
        });
    }

    public function redirectWeb($host, $status)
    {
        return redirect($host . "?status={$status}");
    }


}
