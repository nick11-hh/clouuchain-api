<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class ClientGoodsInfo extends JsonResource
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
            'category_name' => $this->category_name ?? '',
            'brand' => $this->brand,
            'unit' => $this->unit,
            'purchase_url' => $this->purchase_url,
            'cover_image' => $this->cover_image,
            'main_images' => $this->main_images ?? [],
            'options' => $this->options,
            'props' => $this->props,
            'goods_lowest_price' => $this->goods_lowest_price,
            'sale_count' => $this->sale_count,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'skus' => ClientGoodsSkuList::collection($this->skus),
            'detail' => $this->detail,
            'created_at' => (string)$this->created_at
        ];
    }
}
