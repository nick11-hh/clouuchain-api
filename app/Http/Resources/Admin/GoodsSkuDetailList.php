<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class GoodsSkuDetailList extends JsonResource
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
            'sku_id' => $this->sku_id,
            'goods_id' => $this->goods->id ?? 0,
            'goods' => $this->goods,
            'goods_name' => $this->goods->goods_name ?? '',
            'spec_name' => $this->spec_name,
            'spec_info' => $this->spec_info,
            'sale_price' => $this->sale_price,
            'purchase_price' => $this->purchase_price,
            'quote_price' => $this->quote_price,
            'images' => $this->images ?? [],
            'quantity' => $this->quantity,
            'status' => $this->status,
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at,
            'system_sku' => $this->system_sku,
            'sku_name' => $this->sku_name,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'weight' => $this->weight,
            'original_price' => $this->original_price,
            'sku_remark' => $this->sku_remark,
            'purchase_days' => $this->purchase_days,
            'min_purchase_quantity' => $this->min_purchase_quantity,
            'purchase_buyer_id' => $this->purchase_buyer_id,
            'purchase_remark' => $this->purchase_remark,
            'logistics' => $this->logistics,
            'goods_suppliers' => $this->goodsSuppliers,
            'category_name' => $this->goods->category->name ?? '',
            'goods_type' => $this->goods->goods_type ?? 1,
            'goods_type_name' => $this->goods->goods_type_name ?? '',
            'packing_materials_type' => $this->goods->packing_materials_type ?? 0,
            'packing_materials_type_name' => $this->goods->packing_materials_type_name ?? '',
        ];
    }
}
