<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class InventoryStockInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse->warehouse_name ?? '',
            'order_sn' => $this->order_sn,
            'type' => $this->type,
            'type_name' => $this->type_name,
            'method' => $this->method,
            'method_name' => $this->method_name,
            'is_zero' => $this->is_zero,
            'total_quantity' => $this->total_quantity,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'operator_id' => $this->operator_id,
            'operator' => $this->operator,
            'finish_time' => (string)$this->finish_time,
            'remark' => $this->remark,
            'items' => $this->items,
            'created_at' => (string)$this->created_at,
        ];
    }
}
