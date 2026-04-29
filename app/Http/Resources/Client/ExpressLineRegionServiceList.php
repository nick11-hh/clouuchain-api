<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineRegionServiceList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->service->id,
            'name' => $this->service->name,
            'is_forced' => $this->service->is_forced,
            'type' => $this->service->type,
            'value' => $this->value,
            'base_value' => $this->base_value,
            'remark' => $this->service->remark,
        ];
    }
}
