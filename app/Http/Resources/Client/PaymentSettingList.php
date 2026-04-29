<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentSettingList extends JsonResource
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
            'name' => $this->name,
            'pay_logo' => $this->pay_logo,
            'pay_qrcode' => $this->pay_qrcode,
            'pay_account' => $this->pay_account,
            'remark' => $this->remark,
            'currency' => $this->currency,
            'created_at' => $this->created_at,
        ];
    }
}
