<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineRegionRuleList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'conditions' => ExpressLineRuleConditionList::collection($this->conditions),
            'type' => $this->type,
            'charge_mode' => $this->charge_mode,
            'value' => $this->value,
            'min_charge' => $this->min_charge,
            'max_charge' => $this->max_charge,
            'notice' => $this->notice,
            'is_and' => $this->is_and,
        ];
    }
}
