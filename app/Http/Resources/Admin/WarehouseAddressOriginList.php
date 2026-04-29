<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseAddressOriginList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'enabled' => $this->enabled,
            'enabled_name' => $this->enabled_name,
            'warehouse_name' => $this->warehouse_name,
            'receiver_name' => $this->receiver_name ?? '',
            'support_countries' => CountryWithoutAreaList::collection($this->countries),
            'timezone' => $this->timezone ?? '',
            'phone' => $this->phone ?? '',
            'postcode' => $this->postcode ?? '',
            'address' => $this->address,
            'code' => $this->code ?? '',
            'province' => $this->province ?? '',
            'city' => $this->city ?? '',
            'created_at' => (string)($this->created_at ?? ''),
            'index' => $this->custom_sort ?? '',
            'custom_location' => $this->custom_location,
            'auto_location' => $this->auto_location
        ];
    }
}
