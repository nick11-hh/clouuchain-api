<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class PickingOrderList extends JsonResource
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
            'picking_staff_id' => $this->picking_staff_id,
            'staff_name' => $this->admin->username ?? '',
            'picking_sn' => $this->picking_sn,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'type' => $this->type,
            'type_name' => $this->type_name,
            'is_print' => $this->is_print,
            'outbound_orders_count' => $this->outbound_orders_count,
            'complete_time' => (string)$this->complete_time,
            'created_at' => (string)$this->created_at,
        ];
    }
}
