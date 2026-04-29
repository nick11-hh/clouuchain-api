<?php

/**
 * @Author: h9471
 * @Created: 2019/10/29 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class NewCusWelfareInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'new_cus_send' => $this->new_cus_send ?? false,
            'invitor_send' => $this->invitor_send ?? false,
            'invited_send' => $this->invited_send ?? false,
            'name' => $this->name ?? '',
            'amount' => ($this->amount ?? null) !== null ? $this->amount / 100 : '',
            'threshold' => ($this->threshold ?? null) !== null ? $this->threshold / 100 : '',
            'day' => $this->day ?? '',
        ];
    }
}
