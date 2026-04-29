<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'user_id' => $this->user_id ?? '',
            'receiver_name' => $this->receiver_name ?? '',
            'timezone' => $this->timezone ?? '',
            'phone' => $this->phone ?? '',
            'country_name' => $this->country['cn_name'] ?? '',
            'country_id' => (string) ($this->country['id'] ?? ''),
            'city' => $this->city ?? '',
            'street' => $this->street ?? '',
            'door_no' => $this->door_no ?? '',
            'postcode' => $this->postcode ?? '',
            'address' => $this->address ?? '',
            'clearance_code' => $this->clearance_code ?? '',
            'wechat_id' => $this->wechat_id ?? '',
            'id_card' => $this->id_card ?? '',
            'area' => $this->area ?? '',
            'remark' => $this->remark ?? '',
            'email' => $this->email ?? ''
        ];
    }
}
