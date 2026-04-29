<?php

namespace App\Services\Base;


use App\Lib\Code;
use App\Models\AgentCommission;
use App\Models\Custom;
use App\Models\CustomBalance;
use App\Exceptions\AccidentException;

class CommissionService
{

    protected int $customId = 0;

    public function __construct($customId = 0)
    {
        if (empty($customId)) {
            $this->customId = getCustomId();
        } else {
            $this->customId = $customId;
        }
    }

    /*** 新增佣金余额和记录
     * @param $orderAmount numeric 金额
     * @param $proportion numeric 抽佣比例
     * @param $orderSn  string 订单编号
     * @return bool|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     * @throws \Exception
     */
    public function increase($orderAmount, $proportion, $orderSn)
    {
        logger("$orderAmount");
        if ($orderAmount <= 0) {
            return true;
        }
        $custom = Custom::query()->find($this->customId);
        $agentId = $custom->invite_id ?? 0;
        if (empty($agentId)) {
            logger('当前用户非推广用户，用户id：' . $this->customId);
            return true;
        }

        //查询推广人的钱包，加锁
        $balance = CustomBalance::query()->where('custom_id', $agentId)->lockForUpdate()->first();

        if (empty($balance)) throw new AccidentException('钱包不存在', Code::OPERATE_FAIL);

        $commissionAmount = $orderAmount * $proportion / 100;

        //佣金固定金额（美元）
        $fixedAmount = $custom->commission_amount ?? 0;
        if ($fixedAmount > 0) {
            $commissionAmount = $fixedAmount;

            $proportion = 0;//佣金比例调整为0
        }

        $amount = bcmul($commissionAmount, 100);//美元 转 美分

        $balance->commission += round($amount);
        $balance->save();
        $data = [
            'agent_id' => $agentId,
            'custom_id' => $this->customId,
            'order_number' => $orderSn,
            'order_amount' => $orderAmount,
            'proportion' => $proportion,
            'commission_amount' => $commissionAmount,
        ];
        return AgentCommission::query()->create($data);
    }

    /** 提现减少佣金余额
     * @param $amount
     * @return bool
     */
    public function deduction($amount)
    {
        $balance = CustomBalance::query()
            ->where('custom_id', $this->customId)
            ->lockForUpdate()
            ->first();
        $amount = bcmul($amount, 100);
        $balance->commission -= round($amount);
        if ($balance->commission < 0) throw new AccidentException("结算金额大于佣金余额", Code::OPERATE_FAIL);
        return $balance->save();
    }



}

