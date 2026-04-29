<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineServiceList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->service->id,
            'name' => $this->service->name,
            'remark' => $this->service->remark,
            'type' => $this->service->type,
            'is_forced' => $this->service->is_forced,
            'value' => $this->value / 100,
            'base_value' => $this->base_value / 100,
            'created_at' => (string) $this->created_at,
        ];
    }
}
