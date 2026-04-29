<?php

/**
 * @Author: h9471
 * @Created: 2019/12/10 11:41
 */

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class PaymentSettingConnectInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'payment_settings_id' => $this->payment_settings_id,
            'name' => $this->name,
            'content' => $this->content,
            'created_at' => (string) $this->created_at,
        ];
    }
}
