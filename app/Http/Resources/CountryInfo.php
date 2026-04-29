<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CountryInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'index' => $this->index,
            'enabled' => $this->enabled,
            'timezone' => $this->timezone,
            'rgb_color' => $this->rgb_color ?? [0, 0, 0],
            'name_trans' => $this->getOtherTranslations('name'),
        ];
    }
}
