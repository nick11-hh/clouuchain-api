<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineRegionAreaNameList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'country_name' => $this->country_name,
            'area_name' => $this->area_name ?? '',
            'sub_area_name' => $this->sub_area_name ?? '',
        ];
    }
}
