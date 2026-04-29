<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopOrderLineItemList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'title'           => $this->title,
            'variant_title'   => $this->variant_title,
            'variant_id'      => $this->variant_id,
            'vendor'          => $this->vendor,
            'price'           => $this->price,
            'quantity'        => $this->quantity,
            'sku'             => $this->sku,
            'imgs'            => $this->imgs,
            'arrive_quantity' => $this->arrive_quantity ?? 0,
            'created_at'      => (string)$this->created_at,
            'updated_at'      => (string)$this->updated_at,
            'is_delete'       => (string)$this->deleted_at ? 1 : 0,
            'quote_price'     => $this->quote_price,
            'product_url'     => $this->product_url,
            'purchase_price'  => $this->purchase_price,
            'profit'          => $this->profit,
            'logistics_fee'   => $this->logistics_fee,
            'add_type'        => $this->add_type,
            'mapping'         => $this->mapping,
            'properties'      => $this->properties
        ];
    }
}
