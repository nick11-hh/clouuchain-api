<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLinePriceRuleList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'start' => $this->start / 1000,
            'end' => $this->end / 1000,
            'unit_weight' => $this->unit_weight / 1000,
            'first_weight' => $this->first_weight / 1000,
            'created_at' => (string)$this->created_at,
            'type' => $this->type,
            'scale_weight' => $this->scale_weight ? ($this->scale_weight / 1000) : '',
            'unit_price' => ($this->unit_price ?? 0) / 100,
            'base_price' => ($this->base_price ?? 0) / 100,
        ];
    }
}
