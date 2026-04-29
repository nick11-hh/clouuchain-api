<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseStockItemsList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'stock_id' => $this->stock_id,
            'stock' => $this->stock,
            'warehouse_id' => $this->warehouse_id,
            'goods_name' => $this->goods_name,
            'custom_id' => $this->custom_id,
            'total_quantity' => $this->total_quantity,
            'quantity' => $this->quantity,
            'lock_quantity' => $this->lock_quantity,
            'location_id' => $this->location_id,
            'location_code' => $this->location_code,
            'created_at' => (string)$this->created_at,
        ];
    }
}
