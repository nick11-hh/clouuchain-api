<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineRegionServicePriceList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->region->id,
            'region_name' => $this->region->name,
            'areas' => ExpressLineRegionAreaNameList::collection($this->region->areas),
            'value' => $this->value / 100,
            'base_value' => $this->base_value / 100,
        ];
    }
}
