<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use App\Models\ExpressLinePricesModel;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineRegionList extends JsonResource
{
    public function toArray($request)
    {
        return [
                'id' => $this->id,
                'name' => __($this->name),//全国区域翻译
                'index' => $this->index,
                'reference_time' => $this->reference_time,
                'delivery_min_days' => $this->delivery_min_days, //最小送达天数
                'delivery_max_days' => $this->delivery_max_days, //最大送达天数
                // 'areas_count' => $this->areas_count + ($this->type === 2 ? 1 :0),
                'type' => $this->type,
                'areas' => ExpressLineRegionAreaNameList::collection($this->areas),
                'postcode_areas' => ExpressLineRegionPostcodeAreaList::collection($this->postcodeAreas),
                'country' => CommonOriginNameList::make($this->country),
                'enabled' => $this->enabled,
                'mode' => $this->expressLine->mode ?? 0,
                'grades' => ExpressLinePriceRuleList::collection($this->priceRules
                    ->filter(fn($v) => $v->type !== ExpressLinePricesModel::TYPE_UNIT_WEIGHT)
                    ->sortBy->start->values()
                ),
            ] + $this->getTranslatedLocalesDiffAll()->toArray();
    }
}
