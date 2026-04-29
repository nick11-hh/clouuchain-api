<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class ClientGoodsSkuList extends JsonResource
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
            'spec_name' => $this->spec_name,
            'spec_info' => $this->spec_info,
            'sale_price' => $this->sale_price,
            'original_price' => $this->original_price,
            'cost_price' => $this->cost_price,
            'images' => $this->images ?? [],
            'quantity' => $this->quantity,
            'status' => $this->status,
            'created_at' => (string)$this->created_at
        ];
    }
}
