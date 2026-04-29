<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CountryList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cn_name' => $this->cn_name,
            'index' => $this->index,
            'areas' => CountryAreaInfoList::collection($this->areas),
            'enabled' => $this->enabled,
            'hot' => $this->hot,
            'timezone' => $this->timezone,
            'rgb_color' => $this->rgb_color ?? [0, 0, 0],
            'code' => $this->code,
        ];
    }
}
