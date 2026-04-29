<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class RechargeApplyList extends JsonResource
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
            'payment_type_id' => $this->payment_type_id,
            'payment_type_name' => $this->paymentType->name ?? '',
            'apply_amount' => $this->apply_amount / 100,
            'apply_images' => $this->apply_images,
            'apply_remark' => $this->apply_remark,
            'pay_account' => $this->pay_account,
            'confirm_amount' => $this->confirm_amount / 100,
            'confirm_images' => $this->confirm_images,
            'confirm_remark' => $this->confirm_remark,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'serial_no' => $this->serial_no,
            'out_serial_no' => $this->out_serial_no,
            'apply_operator' => $this->apply_operator,
            'created_at' => (string)$this->created_at,
        ];
    }
}
