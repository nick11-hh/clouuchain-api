<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class GoodsSupplierList extends JsonResource
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
            'goods_sku_id' => $this->goods_sku_id,
            'goods_sku' => $this->goodsSku,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->supplier,
            'price' => $this->price,
            'currency' => $this->currency,
            'purchase_type' => $this->purchase_type,
            'purchase_type_name' => $this->purchase_type_name,
            'purchase_url' => $this->purchase_url,
            'purchase_goods_name' => $this->purchase_goods_name,
            'purchase_spec_image' => $this->purchase_spec_image,
            'purchase_spec_name' => $this->purchase_spec_name,
            'purchase_goods_id' => $this->purchase_goods_id,
            'purchase_sku_id' => $this->purchase_sku_id,
            'purchase_spec_id' => $this->purchase_spec_id,
            'status' => $this->status,
            'is_default' => $this->is_default == 1,
            'created_at' => (string)$this->created_at,
        ];
    }
}
