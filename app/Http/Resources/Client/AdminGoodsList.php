<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminGoodsList extends JsonResource
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
            'purchase_url' => $this->purchase_url,
            'cover_image' => $this->cover_image,
            'props' => $this->props,
            'goods_lowest_price' => $this->goods_lowest_price,
            'sale_count' => $this->sale_count,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'is_hot' => $this->is_hot,
            'min_price' => $this->min_price,
            'max_price' => $this->max_price,
            'created_at' => (string)$this->created_at,
            'goods_type' => $this->goods_type,
            'origin_type' => $this->origin_type,
            'packing_materials_type' => $this->packing_materials_type,
            'self_goods' => $this->self_goods,
            'has_select' => $this->hasSelect ? 1 : 0
        ];
    }
}
