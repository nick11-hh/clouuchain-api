<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShopList extends JsonResource
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
            'id'                 => $this->id,
            'customer_id'        => $this->customer_id,
            'customer'           => $this->customer,
            'shop_name'          => $this->shop_name,
            'platform'           => $this->platform,
            'status'             => $this->status,
            'status_name'        => $this->status_name,
            'enable'             => $this->enable,
            'authorize_at'       => $this->authorize_at,
            'shop_url'           => $this->shop_url,
            'created_at'         => (string)$this->created_at,
            'updated_at'         => (string)$this->updated_at,
            'tax'                => $this->tax,
            'european_union_tax' => $this->european_union_tax,
            'united_kingdom_tax' => $this->united_kingdom_tax,
            'norway_tax'         => $this->norway_tax,
            'authorization_type' => $this->authorization_type,
            'fail_info'          => $this->fail_info,
        ];
    }
}
