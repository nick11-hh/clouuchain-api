<?php

/**
 * @Author: h9471
 * @Created: 2021/7/29 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SalePriceInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'scope' => $this->scope,
            'enabled' => $this->enabled,
            'status' => $this->status,
            'user_groups' => $this->userGroups->modelKeys(),
            'member_levels' => $this->memberLevels->modelKeys(),
            'users' => CommonOriginNameList::collection($this->users),
            'user_tags' => $this->userTags->modelKeys(),
            'express_line_ids' => $this->expressLines->modelKeys(),
            'discount' => (float) $this->discount,
            'discount_type' => (float) $this->discount_type,
            'effect_at' => (string) $this->effect_at,
            'expire_at' => (string) $this->expire_at,
            'index' => $this->index,
            'created_at' => (string) $this->created_at,
        ];
    }
}
