<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use App\Models\ExpressLinePrice;
use App\Models\ExpressLinePricesModel;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineBillingConfigInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'first_weight' => ($first = $this->priceRules->first(function ($v) {
                return $v->type == ExpressLinePricesModel::TYPE_FIRST_WEIGHT;
            }))
                ? $first->start / 1000
                : 0,
            'grades' => ExpressLinePriceRuleList::collection($this->priceRules
                ->filter(fn($v) => !in_array($v->type, [ExpressLinePricesModel::TYPE_FIRST_WEIGHT, ExpressLinePricesModel::TYPE_UNIT_WEIGHT]))
                ->sortBy->start->values()
            ),
            'has_factor' => $this->has_factor,
            'factor' => $this->factor,
            'ceil_weight' => $this->ceil_weight,
            'weight_rise' => $this->weight_rise,
            'multi_boxes' => $this->multi_boxes,
            'multi_boxes_ceil' => $this->multi_boxes_ceil,
            'min_weight' => $this->min_weight / 1000,
            'max_weight' => $this->max_weight / 1000,
            'base_mode' => $this->base_mode,
            'mode' => $this->mode,
            'multi_box_min_weight' => $this->multi_box_min_weight / 1000,
            'is_avg_weight' => $this->is_avg_weight,
            'no_throw_condition'=>$this->no_throw_condition,
            'labels' => CommonOriginNameList::collection($this->labels),
            'range' => $this->range ?? 0,
            'weight_factor' => $this->weight_factor ?? 0,
            'weight_trans' => $this->weight_trans ?? 0,
            'payment_weight_int' => $this->payment_weight_int ?? 0,
            'overweight_status' => $this->overweight_status ?? 0,
            'overweight_weight' => ($this->overweight_weight ?? 0) / 1000,
            'overweight_remark' => $this->overweight_remark ?? '',
        ];
    }
}
