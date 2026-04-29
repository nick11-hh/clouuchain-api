<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineRuleList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            //'regions' => CommonOriginNameList::collection($this->regions),
            'region_ids' => $this->regions->modelKeys(),
            'conditions' => ExpressLineRuleConditionList::collection($this->conditions),
            'type' => $this->type,
            'charge_mode' => $this->charge_mode,
            'value' => $this->value / 100,
            'min_charge' => $this->min_charge / 100,
            'max_charge' => $this->max_charge / 100,
            'notice' => $this->notice,
            'is_and' => $this->is_and,
            'created_at' => (string) $this->created_at,
            'name_translations' => $this->getOtherTranslations('name'),
            'notice_translations' => $this->getOtherTranslations('notice'),
            'condition' => $this->condition ?? '',
            'result' => $this->result ?? '',
            'else_result' => $this->else_result ?? '',
        ];
    }
}
