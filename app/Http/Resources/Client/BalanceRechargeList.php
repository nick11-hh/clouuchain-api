<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class BalanceRechargeList extends JsonResource
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
            'type' => $this->type,
            'type_name' => $this->type_name,
            'out_trade_no' => $this->out_trade_no,
            'trade_no' => $this->trade_no,
            'recharge_amount' => $this->recharge_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'created_at' => (string)$this->created_at,
        ];
    }
}
