<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class CommissionList extends JsonResource
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
            'custom_name' => $this->custom->custom_name ?? '',
            'order_amount' => $this->order_amount,
            'commission_amount' => $this->commission_amount,
            'created_at' => (string)$this->created_at,
            'status' => $this->status,
            'status_name' => $this->status_name ?? '',
        ];
    }
}
