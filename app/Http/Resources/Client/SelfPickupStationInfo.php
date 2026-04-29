<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources\Client;

use App\Http\Resources\CommonINameList;
use App\Http\Resources\CommonOriginNameList;
use Illuminate\Http\Resources\Json\JsonResource;

class SelfPickupStationInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country' => CommonINameList::make($this->country),
            'area' => CommonOriginNameList::make($this->area),
            'sub_area' => CommonOriginNameList::make($this->subArea),
            'postcode' => $this->postcode,
            'address' => $this->address,
            'contactor' => $this->contactor ?? '',
            'contact_info' => $this->contact_info,
            'opening_hours' => $this->opening_hours ?? '',
            'announcement' => $this->announcement ?? '',
            'lon' => $this->lon ?? '',
            'lat' => $this->lat ?? '',
            'limit_one_weight' => $this->limit_one_weight,
            'limit_many_weight' => $this->limit_many_weight,
            'limit_length' => $this->limit_length,
            'is_delivery' => $this->is_delivery,
            'index' => $this->index,
            'created_at' => (string) $this->created_at,
        ];
    }
}
