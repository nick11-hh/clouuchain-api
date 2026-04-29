<?php


namespace App\Services;


use App\Events\RechargeSuccess;
use App\Models\BalanceRechargeRecord;
use App\Models\DefaultRechargeAmount;
use App\Models\TransactionRecord;
use Illuminate\Support\Facades\DB;

trait RechargeTrait
{
    use IncomeOutlayRecordTrait;
    public function rechargeSuccess(BalanceRechargeRecord $record, $outerSerialNo, string $resource)
    {
        info('充值回调进入此处');
        if (empty($outerSerialNo)) {
            info('out_serial_no is null');
            return true;
        };
        $comRecord = null;
        DB::beginTransaction();
        try {
            //更新审核状态
            $record->status = BalanceRechargeRecord::CHECK_PASS;
            $record->save();
            //充值
            $record->updateTransactionRecord($outerSerialNo, $resource);
            //充值赠送-更新流水和余额
            $defaultRechargeAmountConfig = DefaultRechargeAmount::query()->where('amount', '<=', bcdiv($record->confirm_amount, 100, 2))->orderByDesc('amount')->first();
            info(config('app.env'));
            if ($defaultRechargeAmountConfig && ($defaultRechargeAmountConfig->complimentary_amount > 0)) {
                info('进入充值赠送');
                $comRecord = clone $record;
                $comRecord->confirm_amount = $defaultRechargeAmountConfig->complimentary_amount * 100;
                $comRecord->updateTransactionRecord($outerSerialNo, $resource, TransactionRecord::COMPLIMENTARY_RECHARGE);
            }
            //充值获得积分
            $this->createByBalanceRecharge($record->user_id, bcdiv($record->confirm_amount, 100, 2), $record->serial_no);
        } catch (\Exception $e) {
            DB::rollBack();
            app('log')->info('支付回调出错,错误原因为:' . $e->getMessage());
            info($e->getMessage(),['exception_line' => $e->getTrace()]);
            return false;
        }
        DB::commit();
        event(new RechargeSuccess($record));
        !empty($comRecord) && event(new RechargeSuccess($comRecord));
        return true;
    }
}
