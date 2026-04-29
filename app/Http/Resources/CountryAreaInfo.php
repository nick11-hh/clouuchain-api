<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CountryAreaInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'postcode' => $this->postcode,
            'enabled' => $this->enabled,
            'country_id' => $this->country_id,
            'parent_id' => $this->parent_id,
            'api_code' => $this->api_code,
            'name_translations' => $this->getOtherTranslations('name'),
        ];
    }
}
