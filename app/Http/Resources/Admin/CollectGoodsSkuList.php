<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CollectGoodsSkuList extends JsonResource
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
            'prop_id' => $this->prop_id,
            'spec_name' => $this->spec_name,
            'spec_name_cn' => $this->spec_name_cn,
            'spec_info' => $this->spec_info,
            'sale_price' => $this->sale_price,
            'compare_price' => $this->compare_price,
            'images' => $this->images ?? [],
            'quantity' => $this->quantity,
            'status' => $this->status,
            'created_at' => (string)$this->created_at,
            'length' => $this->length ?? 0,
            'width' => $this->width ?? 0,
            'height' => $this->height ?? 0,
            'weight' => $this->weight ?? 0,
            'purchase_price' => $this->purchase_price ?? 0,
            'profit' => $this->profit ?? 0,
            'profit_margin' => $this->profit_margin,
            'quote_price' => $this->quote_price ?? 0,
        ];
    }
}
