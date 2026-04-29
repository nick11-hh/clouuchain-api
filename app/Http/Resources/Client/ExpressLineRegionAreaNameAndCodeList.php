<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineRegionAreaNameAndCodeList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'country_id' => $this->country_id,
            'country_code' => $this->country?->code ?? '',
            'country' => $this->country_name,
            'area_id' => $this->area_id,
            'area' => $this->area_name ?? '',
            'sub_area_id' => $this->sub_area_id,
            'sub_area' => $this->sub_area_name ?? '',
        ];
    }
}
