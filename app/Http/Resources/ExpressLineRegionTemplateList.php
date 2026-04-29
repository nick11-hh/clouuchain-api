<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineRegionTemplateList extends JsonResource
{
    public function toArray($request)
    {
        return [
                'id' => $this->id,
                'name' => $this->name,
                'reference_time' => $this->reference_time,
                'areas_count' => $this->areas_count + ($this->type === 2 ? 1 :0),
                'type' => $this->type,
                'areas' => ExpressLineRegionAreaNameList::collection($this->areas),
                'postcode_areas' => ExpressLineRegionPostcodeAreaList::collection($this->postcodeAreas),
                'country' => CommonOriginNameList::make($this->country),
                'enabled' => $this->enabled,
            ] + $this->getTranslatedLocalesDiffAll()->toArray();
    }
}
