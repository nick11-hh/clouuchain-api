<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class GoodsDiscountRuleList extends JsonResource
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
            'code' => $this->code,
            'customer_id' => $this->customer_id,
            'custom' => $this->custom,
            'staff_id' => $this->staff_id,
            'staff_name' => $this->staff->name ?? '-',
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'items' => $this->items,
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at
        ];
    }
}
