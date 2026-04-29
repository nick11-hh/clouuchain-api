<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class PlatformProductSkuList extends JsonResource
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
            'product_id' => $this->product_id,
            'platform_product_id' => $this->platform_product_id,
            'platform_sku_id' => $this->platform_sku_id ?? '',
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'title' => $this->title,
            'option' => $this->option,
            'price' => $this->price,
            'shipping_fee' => (float) ($this->shipping_fee ?? 0),
            'inventory_quantity' => $this->inventory_quantity,
            'compare_at_price' => $this->compare_at_price,
            'inventory_item_id' => $this->inventory_item_id,
            'image' => $this->images[0] ?? '',
            'mapping' => $this->mapping,
            'apply_mapping' => $this->applyMapping,
        ];
    }
}
