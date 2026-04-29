<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseStockInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse->warehouse_name ?? '',
            'goods_id' => $this->goods_id,
            'goods_name' => $this->goods_name,
            'sku_image' => $this->sku_image ?? '',
            'sku_id' => $this->sku_id,
            'spec_name' => $this->spec_name,
            'sku' => $this->sku,
            'total_quantity' => $this->total_quantity,
            'quantity' => $this->quantity,
            'lock_quantity' => $this->lock_quantity,
            'items' => WarehouseStockItemsList::collection($this->items ?? []),
            'created_at' => (string)$this->created_at,
        ];
    }
}
