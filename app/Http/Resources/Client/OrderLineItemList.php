<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderLineItemList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'title'          => $this->title,
            'variant_title'  => $this->variant_title,
            'vendor'         => $this->vendor,
            'price'          => $this->price,
            'quantity'       => $this->quantity,
            'sku'            => $this->sku,
            'imgs'           => $this->imgs,
            'created_at'     => (string)$this->created_at,
            'updated_at'     => (string)$this->updated_at,
            'is_delete'      => (string)$this->deleted_at ? 1 : 0,
            'quote_price'    => $this->quote_price,
            'purchase_price' => $this->purchase_price,
            'profit'         => $this->profit,
            'logistics_fee'  => $this->logistics_fee,
        ];
    }
}
