<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CollectGoodsList extends JsonResource
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
            'spu' => $this->spu,
            'goods_name' => $this->goods_name,
            'category_id' => $this->category_id,
            'category_name' => $this->category->name ?? '',
            'brand' => $this->brand,
            'unit' => $this->unit,
            'purchase_price' => $this->purchase_price,
            'collect_url' => $this->collect_url,
            'cover_image' => $this->cover_image,
            'props' => $this->props,
            'status' => $this->status,
            'status_name' => $this->status_name ?? '-',
            'created_at' => (string)$this->created_at,
            'goods_type' => $this->goods_type ?? 1,
            'goods_type_name' => $this->goods_type_name ?? '',
            'packing_materials_type' => $this->packing_materials_type ?? 0,
            'packing_materials_type_name' => $this->packing_materials_type_name ?? '',
            'supplier_name' => $this->supplier->supplier_name ?? '',
        ];
    }
}
