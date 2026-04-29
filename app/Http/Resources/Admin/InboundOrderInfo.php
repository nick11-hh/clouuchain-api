<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class InboundOrderInfo extends JsonResource
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
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse->warehouse_name ?? '',
            'custom_id' => $this->custom_id,
            'custom_name' => $this->custom->custom_name ?? '本企业',
            'inbound_sn' => $this->inbound_sn,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'logistics_sn' => $this->logistics_sn,
            'expect_time' => (string)$this->expect_time,
            'sign_time' => (string)$this->sign_time,
            'inbound_time' => (string)$this->inbound_time,
            'remark' => $this->remark ?? '',
            'created_at' => (string)$this->created_at,
            'items' => InboundOrderItemList::collection($this->items),
            'quantity_sum' => (int)$this->items_sum_quantity,
            'sign_quantity_sum' => (int)$this->items_sum_sign_quantity,
            'inbound_type_name' => $this->inbound_type_name ?? '',
        ];
    }
}
