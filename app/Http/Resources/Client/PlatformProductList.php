<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class PlatformProductList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        //是否存在SKU报价记录
        $applyMappingExists = 0;
        if ($this->skus) {
            $this->skus->map(function ($item) use (&$applyMappingExists) {
                if ($item->applyMapping) {
                    $applyMappingExists = 1;
                }
            });
        }

        return [
            'id' => $this->id,
            'shop_id' => $this->shop_id,
            'shop_type' => $this->shop_type,
            'shop_name' => $this->shop->shop_name ?? '',
            'shop_url' => $this->shop->shop_url ?? '',
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_type' => $this->product_type,
            'image' => $this->images[0]?? '',
            'tags' => $this->tags,
            'status' => $this->status,
            'min_price' => $this->min_price,
            'max_price' => $this->max_price,
            'published_at' => (string)$this->published_at,
            'created_at' => (string)$this->created_at,
            'quote_status' => $this->quote_status,
            'quote_status_name' => $this->quote_status_name,
            'quote_remark' => $this->quote_remark,
            'logistics_channel_id' => $this->logistics_channel_id,
            'logistics_channel' => $this->logisticsChannel,
            'country_id' => $this->country_id,
            'country' => $this->country,
            'reference_time' => $this->reference_time,
            'skus' => PlatformProductSkuList::collection($this->skus ?? []),
            'custom_id' => $this->custom_id ?? 0,
            'custom_name' => $this->custom->custom_name ?? '',
            'apply_country_ids' => $this->apply_country_ids ?? [],
            'apply_mapping_exists' => $applyMappingExists,
        ];
    }
}
