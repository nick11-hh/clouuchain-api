<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomInfo extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'custom_name' => $this->custom_name,
            'custom_email' => $this->custom_email,
            'custom_phone' => $this->custom_phone,
            'customer_number' => $this->customer_number,
            'group_id' => $this->group_id,
            'group' => $this->customGroup,
            'group_name' => $this->customGroup->group_name ?? '',
            'balance' => (string)bcdiv($this->balance->balance, 100, 2),//余额
            'credit_line' => $this->credit_line,//授信
            'residual_credit' => $this->residual_credit,//剩余额度
            'frozen_limit' => $this->frozen_limit,//冻结
            'cumulative_frozen' => $this->cumulative_frozen,//累计冻结金额
            'cumulative_unfrozen' => $this->cumulative_unfrozen,//累计解冻金额
            'cumulative_top_up' => $this->cumulative_top_up,//累计充值金额
            'consume_amount' => $this->consume_amount,//累计扣费金额
        ];
    }
}
