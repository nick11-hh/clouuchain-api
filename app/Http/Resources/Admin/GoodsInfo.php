<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class GoodsInfo extends JsonResource
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
            'goods_name_cn' => $this->goods_name_cn,
            'alias' => $this->alias,
            'category_id' => $this->category_id,
            'category_name' => $this->category->name ?? '',
            'brand' => $this->brand,
            'unit' => $this->unit,
            'purchase_price' => $this->purchase_price,
            'purchase_url' => $this->purchase_url,
            'cover_image' => $this->cover_image,
            'main_images' => $this->main_images ?? [],
            'options' => $this->options,
            'props' => $this->props,
            'goods_lowest_price' => $this->goods_lowest_price,
            'sale_count' => $this->sale_count,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'is_hot' => $this->is_hot,
            'skus' => GoodsSkuList::collection($this->skus),
            'logistics' => $this->logistics,
            'detail' => $this->detail,
            'addr' => $this->addr,
            'created_at' => (string)$this->created_at,
            'developer_id' => $this->developer_id,
            'goods_type' => $this->goods_type ?? 1,
            'packing_materials_type' => $this->packing_materials_type ?? 0,
            'main_video' => $this->main_video ?? '',
            'self_goods' => $this->self_goods,
        ];
    }
}
