<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineRegionPriceList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'start' => $this->start,
            'end' => $this->end,
            'price' => $this->price,
            'first_weight' => $this->first_weight,
            'unit_weight' => $this->unit_weight,
        ];
    }
}
