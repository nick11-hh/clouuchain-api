<?php

namespace App\Http\Resources\Admin;

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
            'custom' => CustomInfo::make($this->custom),
            'payment_type_id' => $this->payment_type_id,
            'payment_type_name' => $this->paymentType ? $this->paymentType->name : '',
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
            'confirm_operator' => $this->confirm_operator,
            'created_at' => (string)$this->created_at,
            'pay_amount' => $this->pay_amount / 100,
            'currency' => $this->currency,
            'revocation_remark' => $this->revocation_remark,
            'revocation_at' => (string)$this->revocation_at,
            'revocation_no' => $this->revocation_no,
            'revocation_operator_name' => $this->revocationOperator ? $this->revocationOperator->username : '',
            'check_admin_id' => $this->check_admin_id ?? 0,
            'check_images' => $this->check_images ?? [],
            'check_desc' => $this->check_desc ?? '',
            'check_status' => $this->check_status ?? 0,
            'check_status_name' => $this->check_status_name,
            'check_time' => (string)$this->check_time ?? '',
            'pay_method' => $this->pay_method,
            'pay_method_name' => $this->payMethod->name ?? '-',
        ];
    }
}
