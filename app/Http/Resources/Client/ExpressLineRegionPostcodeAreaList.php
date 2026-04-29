<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineRegionPostcodeAreaList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'start' => $this->start,
            'end' => $this->end,
        ];
    }
}
