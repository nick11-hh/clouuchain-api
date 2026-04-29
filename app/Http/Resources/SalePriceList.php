<?php

/**
 * @Author: h9471
 * @Created: 2021/7/29 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SalePriceList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'scope' => $this->scope,
            'status' => $this->status,
            'enabled' => $this->enabled,
            'express_lines' => CommonOriginNameList::collection($this->expressLines),
            'effect_at' => (string) $this->effect_at,
            'expire_at' => (string) $this->expire_at,
            'index' => $this->index,
            'created_at' => (string) $this->created_at,
        ];
    }
}
