<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseStockChangeList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'custom_id' => $this->custom_id,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse->warehouse_name ?? '',
            'stock_id' => $this->stock_id,
            'goods_name' => $this->goods_name,
            'spec_name' => $this->spec_name,
            'sku' => $this->sku,
            'sku_image' => $this->sku_image,
            'location_id' => $this->location_id,
            'location_code' => $this->location_code,
            'operate_sn' => $this->operate_sn,
            'source' => $this->source,
            'source_name' => $this->source_name,
            'change_type' => $this->change_type,
            'change_type_name' => $this->change_type_name,
            'quantity' => $this->quantity,
            'created_at' => (string)$this->created_at,
        ];
    }
}
