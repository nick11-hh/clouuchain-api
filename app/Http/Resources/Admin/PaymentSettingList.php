<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentSettingList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
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
            'enabled' => $this->enabled,
            'enabled_name' => $this->enabled_name,
            'currency' => $this->currency,
            'created_at' => (string)$this->created_at,
            'payment_setting_connection' => $this->PaymentSettingConnection,
        ];
    }
}
