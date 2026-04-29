<?php

namespace App\Http\Resources\Client;

use App\Http\Resources\CountryList;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformProductQuoteDetail extends JsonResource
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
            'custom_id' => $this->custom_id ?? 0,
            'custom_name' => $this->custom->custom_name ?? '',
            'shop_id' => $this->shop_id,
            'shop_type' => $this->shop_type,
            'shop_name' => $this->shop->shop_name ?? '',
            'shop_url' => $this->shop->shop_url ?? '',
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_type' => $this->product_type,
            'image' => $this->images[0] ?? '',
            'tags' => $this->tags,
            'status' => $this->status,
            'quote_status' => $this->quote_status,
            'quote_status_name' => $this->quote_status_name ?? '-',
            'quote_remark' => $this->quote_remark,
            'published_at' => (string)$this->published_at,
            'created_at' => (string)$this->created_at,
            'apply_country_ids' => $this->apply_country_ids ?? [],
            'country_list' => CountryList::collection($this->country_list ?? []),
            'sku_list' => PlatformProductSkuDetail::collection($this->skus ?? []),
        ];
    }
}
