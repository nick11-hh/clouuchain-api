<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserAddressList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'user_id' => $this->user_id,
            'user_uid' => $this->user->uid ?? '',
            'receiver_name' => $this->receiver_name,
            'timezone' => $this->timezone,
            'phone' => $this->phone,
            'spare_phone' => $this->spare_phone,
            'country' => [
                'id' => $this->country_id,
                'name' => $this->country->name ?? '',
            ],
            'area_id' => $this->area_id,
            'sub_area_id' => $this->sub_area_id,
            'low_area_id' => $this->low_area_id,
            'city' => $this->city ?? '',
            'street' => $this->street ?? '',
            'door_no' => $this->door_no ?? '',
            'postcode' => $this->postcode ?? '',
            'address' => $this->address ?? '',
            'clearance_code' => $this->clearance_code ?? '',
            'wechat_id' => $this->wechat_id ?? '',
            'id_card' => $this->id_card ?? '',
            'address_area' => $this->area,
            'area' => $this->getRelationValue('area'),
            'sub_area' => $this->subArea,
            'low_area' => $this->lowArea,
            'province' => $this->province ?? '',
            'district' => $this->district ?? '',
            'email' => $this->email ?? '',
            'is_cn_address' => $this->is_cn_address,
            'station_id' => $this->station_id,
            'station' => $this->station,
            'tags' => CommonOriginNameList::collection($this->tags),
            'company' => $this->company,
            'created_at' => (string) $this->created_at,
            'custom_tags' => $this->custom_tags,
        ];
    }
}
