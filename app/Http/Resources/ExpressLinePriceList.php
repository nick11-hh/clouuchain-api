<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLinePriceList extends JsonResource
{
    public function toArray($request)
    {
        return [
                'id' => $this->id,
                'start' => $this->start / 1000,
                'end' => $this->end / 1000,
                'cost_price' => $this->cost_price / 100,
                'sale_price' => $this->sale_price / 100,
                'created_at' => (string)$this->created_at,
            ];
    }
}
