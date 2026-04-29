<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CollectGoodsInfo extends JsonResource
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
            'alias' => $this->alias,
            'category_id' => $this->category_id,
            'category_name' => $this->category->name ?? '',
            'brand' => $this->brand,
            'unit' => $this->unit,
            'purchase_price' => $this->purchase_price,
            'collect_url' => $this->collect_url,
            'cover_image' => $this->cover_image,
            'main_images' => $this->main_images ?? [],
            'options' => $this->options,
            'props' => $this->props,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'is_hot' => $this->is_hot,
            'skus' => CollectGoodsSkuList::collection($this->skus),
            'detail' => $this->detail,
            'created_at' => (string)$this->created_at,
            'goods_type' => $this->goods_type ?? 1,
            'packing_materials_type' => $this->packing_materials_type ?? 0,
            'supplier_id' => $this->supplier_id ?? 0,
        ];
    }
}
