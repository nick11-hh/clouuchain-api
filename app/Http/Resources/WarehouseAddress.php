<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseAddress extends JsonResource
{
    public function toArray($request)
    {
        return [
                'id' => $this->id,
                'enabled' => $this->enabled,
                'warehouse_name' => $this->warehouse_name,
                'receiver_name' => $this->receiver_name ?? '',
                'support_countries' => CountryWithoutAreaList::collection($this->countries),
                'timezone' => $this->timezone ?? '',
                'phone' => $this->phone ?? '',
                'postcode' => $this->postcode ?? '',
                'address' => $this->address ?? '',
                'code' => $this->code ?? '',
                'tips' => $this->tips ?? '',
                'auto_location' => $this->auto_location,
                'short_address' => $this->short_address ?? '',
                'mode' => $this->mode,
                'province' => $this->province ?? '',
                'city' => $this->city ?? '',
                'district' => $this->district ?? '',
                'created_at' => (string)($this->created_at ?? ''),
                'index' => $this->custom_sort ?? '',
                'free_store_days' => $this->free_store_days,
                'store_fee' => $this->store_fee / 100,
                'off_shelf_status' => $this->off_shelf_status,
                'big_rule' => $this->big_rule,
                'is_stg' => $this->is_stg,
                'location_size' => [
                    'length' => $this->location_size['length'] ?? null,
                    'width' => $this->location_size['width'] ?? null,
                    'height' => $this->location_size['height'] ?? null,
                ],
                'custom_location' => $this->custom_location ?? 0,
                'location_weight' => $this->location_weight === null ? null : $this->location_weight/ 1000,
                'size_rule' => $this->size_rule ?? 0,
                'weight_rule' => $this->weight_rule ?? 0,
            ] + $this->getTranslatedLocalesDiffAll()->toArray();
    }
}
