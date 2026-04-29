<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CreditCardRechargeList extends JsonResource
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
            'transaction_id' => $this->transaction_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'created_at' => (string)$this->created_at,
            'custom' => CustomInfo::make($this->custom),
            'check_status'=>$this->check_status,
            'check_images'=>json_decode($this->check_images, true) ?? [],
            'check_admin_id'=>$this->check_admin_id ?? '',
            'check_desc'=>$this->check_desc ?? '',
            'check_time'=>$this->check_time ?? '',
            'check_admin_name'=>$this->admin->name ?? '',
        ];
    }

}
