<?php

namespace App\Services\Base;

use App\Lib\Code;
use App\Models\BalanceRecord;
use App\Models\Custom;
use App\Models\CustomBalance;
use App\Models\SystemConfig;
use App\Exceptions\AccidentException;
use setasign\Fpdi\PdfParser\Filter\Ascii85Exception;

class BalanceService
{

    protected int $customId = 0;

    protected string $orderSn;

    protected int $relationId = 0;

    protected string $remark = '';

    protected string $outSerialNo = '';

    protected bool $checkBalance = true;

    protected array $attachmentFiles = [];

    public function __construct($customId = 0)
    {
        if (empty($customId)) {
            $this->customId = getCustomId();
        } else {
            $this->customId = $customId;
        }
        if (empty($this->customId)) throw new AccidentException('未知客户的余额变更', Code::OPERATE_FAIL);
    }

    /** 增加余额
     * @param $amount number 金额（元）
     * @param $sourceType int 来源  BalanceRecord::sourceList
     * @param $orderSn string  订单编号
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     * @throws \Exception
     */
    public function increase($amount, $sourceType, $orderSn, $extra = [])
    {
        $this->orderSn = $orderSn;
        return $this->balanceChange(BalanceRecord::CHANGE_INCREASE, $amount, $sourceType, $extra);
    }

    /** 扣除余额
     * @param $amount number 金额（元）
     * @param $sourceType int 来源  BalanceRecord::sourceList
     * @param $orderSn string  订单编号
     * @param array $extra 额外参数
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     * @throws \Exception
     */
    public function deduction($amount, $sourceType, $orderSn, $extra = [])
    {
        $this->orderSn = $orderSn;
        return $this->balanceChange(BalanceRecord::CHANGE_DEDUCTION, $amount, $sourceType, $extra);
    }

    /** 设置备注
     * @param $outSerialNo
     * @return BalanceService
     */
    public function setRemark($remark)
    {
        $this->remark = $remark;
        return $this;
    }

    /** 设置外部流水号
     * @param $outSerialNo
     * @return BalanceService
     */
    public function setOutSerialNo($outSerialNo)
    {
        $this->outSerialNo = $outSerialNo;
        return $this;
    }

    /** 设置是否检查余额充足
     * @param $isCheck
     * @return BalanceService
     */
    public function setCheckBalance($isCheck)
    {
        $this->checkBalance = $isCheck;
        return $this;
    }

    /** 设置关联id
     * @param $relationId
     * @return BalanceService
     */
    public function setRelationId($relationId)
    {
        $this->relationId = $relationId;
        return $this;
    }

    public function setAttachmentFiles($attachmentFiles)
    {
        $this->attachmentFiles = $attachmentFiles;
        return $this;
    }

    //===============================================================================//

    /** 更改余额
     * @param $changeType
     * @param $amount
     * @param $sourceType
     * @param array $extra
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     * @throws \Exception
     */
    protected function balanceChange($changeType, $amount, $sourceType, $extra = [])
    {
        if ($amount <= 0) throw new AccidentException('金额不能小于等于0', Code::OPERATE_FAIL);
        $custom = Custom::query()->findOrFail($this->customId);
        $balance = CustomBalance::query()
            ->where('custom_id', $this->customId)
            ->lockForUpdate()
            ->first();
        if (empty($balance)) throw new AccidentException('钱包不存在', Code::OPERATE_FAIL);
        $topUp = $amount;
        // 统一金额精度：先保留2位小数，再转换为分，避免等额支付时出现浮点误差
        $amount = (int) bcmul(number_format((float) $amount, 2, '.', ''), '100', 0);
        $credit_line = (int) bcmul(number_format((float) $custom->credit_line, 2, '.', ''), '100', 0);
        $frozen_limit = (int) bcmul(number_format((float) $custom->frozen_limit, 2, '.', ''), '100', 0);
        if ($changeType === BalanceRecord::CHANGE_INCREASE) {
            $balance->balance += round($amount);

//            //已使用额度
//            $useCreditLine = $custom->credit_line - $custom->residual_credit;
//
//            //调整剩余额度
//            if ($useCreditLine > 0) {
//                $custom->residual_credit += $amount / 100;
//
//                //剩余额度大于总额度时 取总额度为剩余额度
//                if ($custom->residual_credit > $custom->credit_line) {
//                    $custom->residual_credit = $custom->credit_line;
//                }
//
//                $custom->save();
//            }
            if (in_array($sourceType, [1, 4, 5])) {
                $custom->cumulative_top_up += round($topUp);
                $custom->save();
            }
            if ($sourceType === BalanceRecord::SOURCE_ADJUST_CREDIT_LIMIT) {
                $extra['actual_amount'] = bcadd($credit_line, $amount);
            }
            if ($sourceType === BalanceRecord::SOURCE_ADJUST_FROZEN_LIMIT) {
                $extra['actual_amount'] = bcsub($frozen_limit, $amount);
            }

        } else {
            if ($this->checkBalance && $balance->balance < $amount) {
                if ($sourceType === BalanceRecord::SOURCE_ADJUST_CREDIT_LIMIT) {
                    throw new AccidentException('调整降低信用额度不能大于账户余额', Code::OPERATE_FAIL);
                }
                $originAmount = bcdiv($amount, 100, 2);
                $originBalance = bcdiv($balance->balance, 100, 2);

                //管理端未开启信用额度或者客户信用额度为0
                if ($custom->credit_line <= 0) {
                    $message = sprintf(__('余额不足，需要扣除 %s，当前余额为 %s'), $originAmount, $originBalance);
                    throw new AccidentException($message, Code::OPERATE_FAIL);
                }

                //客户余额不支持支付时，先将余额计算到信用额度中
                if ($balance->balance > 0) {
                    $custom->residual_credit += $balance->balance / 100;
                }

                //剩余额度小于支付金额
                if ($custom->residual_credit < $originAmount) {
                    $message = sprintf(__('信用额度不足，需要扣除 %s，当前剩余额度为 %s'), $originAmount, $custom->residual_credit);
                    throw new AccidentException($message, Code::OPERATE_FAIL);
                }

                //剩余额度
                $custom->residual_credit -= bcdiv($amount, 100, 2);
                $custom->residual_credit = ($custom->residual_credit > 0 ) ? $custom->residual_credit : 0;
            }

            $balance->balance -= round($amount);//客户余额
            if (!in_array($sourceType, [7, 10, 11])) {
                $custom->consume_amount += $amount / 100;//消费总额
            }
            if ($sourceType === BalanceRecord::SOURCE_RECHARGE_REVOCATION) {
                $custom->cumulative_top_up -= round($topUp);
            }
            if ($sourceType === BalanceRecord::SOURCE_ADJUST_CREDIT_LIMIT) {
                $extra['actual_amount'] = bcsub($credit_line, $amount);
            }
            if ($sourceType === BalanceRecord::SOURCE_ADJUST_FROZEN_LIMIT) {
                $extra['actual_amount'] = bcadd($frozen_limit, $amount);
            }
//            dd($custom->toArray(),$extra);
            $custom->save();
        }

        $extra['after_change_balance'] = $balance->balance;
        $balance->save();

        return $this->createBalanceRecord($changeType, $amount, $sourceType, $extra);
    }

    /**
     * @param $changeType
     * @param $amount
     * @param $sourceType
     * @param array $extra
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    protected function createBalanceRecord($changeType, $amount, $sourceType, $extra = [])
    {
        $data = [
            'custom_id' => $this->customId,
            'type' => $changeType,
            'source_type' => $sourceType,
            'amount' => $amount,
            'after_change_balance' => $extra['after_change_balance'] ?? 0,
            'relation_id' => $this->relationId,
            'relation_type' => BalanceRecord::SOURCE_RELATION_TYPE[$sourceType] ?? '',
            'order_sn' => $this->orderSn,
            'remark' => $this->remark,
            'serial_no' => BalanceRecord::getSerialNo($sourceType),
            'out_serial_no' => $this->outSerialNo,
            'actual_amount' => $extra['actual_amount'] ?? 0,
            'charge_type_id' => $extra['charge_type_id'] ?? 0,
            'operate_admin_id' => $extra['operate_admin_id'] ?? 0,
            'attachment_files' => $this->attachmentFiles,
        ];
        return BalanceRecord::query()->create($data);
    }

}

