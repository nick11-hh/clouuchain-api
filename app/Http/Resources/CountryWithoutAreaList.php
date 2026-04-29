<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CountryWithoutAreaList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'cn_name' => $this->iName,
            'index' => $this->index,
            'areas_count' => $this->areas_count,
            'timezone' => $this->timezone,
            'enabled' => $this->enabled,
            'rgb_color' => $this->rgb_color ?? [0, 0, 0],
        ];
    }
}
