<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class OnlineRechargeList extends JsonResource
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
            'custom_id' => $this->custom_id ?? 0,
            // 'custom' => $this->custom ?? '',
            'custom' => CustomInfo::make($this->custom),
            'type' => $this->type ?? '',
            'type_name' => $this->type_name ?? '',
            'out_trade_no' => $this->out_trade_no ?? '',
            'trade_no' => $this->trade_no ?? '',
            'recharge_amount' => $this->recharge_amount ?? 0,
            'pay_amount' => $this->pay_amount ?? 0,
            'service_charge_amount' => $this->service_chage_amount ?? 0,
            'currency' => $this->currency ?? '',
            'status' => $this->status ?? 0,
            'status_name' => $this->status_name ?? '',
            'created_at' => (string)$this->created_at ?? '',
            'updated_at' => (string)$this->updated_at ?? '',
            'check_admin_id' => $this->check_admin_id ?? 0,
            'check_images' => $this->check_images ?? [],
            'check_desc' => $this->check_desc ?? '',
            'check_status' => $this->check_status ?? 0,
            'check_status_name' => $this->check_status_name,
            'check_time' => (string)$this->check_time ?? '',
        ];
    }
}
