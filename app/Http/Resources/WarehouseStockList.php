<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseStockList extends JsonResource
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
            'in_transit_quantity' => $this->in_transit_quantity,
            'created_at' => (string)$this->created_at,
            'goods_type' => $this->goods_type ?? 1,
            'goods_type_name' => $this->goods_type_name ?? '',
            'packing_materials_type' => $this->packing_materials_type ?? 0,
            'packing_materials_type_name' => $this->packing_materials_type_name ?? '',
            'customer_id' => $this->custom_id ?? 0,
            'customer_name' => $this->customer->custom_name ?? '',
        ];
    }
}
