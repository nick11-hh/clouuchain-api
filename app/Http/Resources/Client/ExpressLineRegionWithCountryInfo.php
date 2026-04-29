<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineRegionWithCountryInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'reference_time' => $this->reference_time,
            'enabled' => $this->enabled,
            'prices' => ExpressLineRegionPriceList::collection($this->prices->sortBy([['type' , 'asc'], ['start', 'asc']])),
            'services' => ExpressLineRegionServiceList::collection($this->servicePrices),
            'rules' => ExpressLineRegionRuleList::collection($this->rules),
            'areas' => ExpressLineRegionAreaNameAndCodeList::collection($this->areas),
            'postcode_areas' => ExpressLineRegionPostcodeAreaList::collection($this->postcodeAreas),
        ];
    }
}
