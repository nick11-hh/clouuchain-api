<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CommissionWithdrawInfo extends JsonResource
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
            'custom' => $this->custom ?? null,
            'withdraw_amount' => $this->withdraw_amount,
            'withdraw_type' => $this->withdraw_type,
            'withdraw_type_name' => $this->withdraw_type_name,
            'withdraw_account' => $this->withdraw_account,
            'custom_remark' => $this->custom_remark,
            'custom_images' => $this->custom_images,
            'confirm_amount' => $this->confirm_amount,
            'serial_no' => $this->serial_no,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'withdraw_status' => $this->withdraw_status,
            'withdraw_status_name' => $this->withdraw_status_name,
            'commission' => $this->commission,
            'created_at' => (string)$this->created_at,
            'confirm_remark' => $this->confirm_remark,
        ];
    }
}
