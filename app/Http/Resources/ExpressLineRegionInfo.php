<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use App\Models\ExpressLinePricesModel;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineRegionInfo extends JsonResource
{
    public function toArray($request)
    {
        $postData = $this->country_id ? [[
            'id' => $this->country_id,
            'name' => $this->country->name ?? '',
            'children' => ExpressLineRegionPostcodeAreaList::collection($this->postcodeAreas)
        ]] : [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'index' => $this->index,
            'reference_time' => $this->reference_time,
            'delivery_min_days' => $this->delivery_min_days, //最小送达天数
            'delivery_max_days' => $this->delivery_max_days, //最大送达天数
            'type' => $this->type,
            'areas' => ExpressLineRegionAreaList::collection($this->areas),
            'postcode_areas' => ExpressLineRegionPostcodeAreaList::collection($this->postcodeAreas),
            'country_id' => $this->country_id,
            'partition_area_data' => ExpressLineRegionPartitionList::collection($this->partitions),
            'partition_post_data' => $postData,
            'grades' => ExpressLinePriceRuleList::collection($this->priceRules
                ->filter(fn($v) => $v->type !== ExpressLinePricesModel::TYPE_UNIT_WEIGHT)
                ->sortBy->start->values()
            ),
            'minimum_chargeable_weight' => $this->minimum_chargeable_weight ? $this->minimum_chargeable_weight / 1000 : '',
        ];
    }
}
