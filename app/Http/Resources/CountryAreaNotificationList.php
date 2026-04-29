<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CountryAreaNotificationList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'areas' => CountryAreaWithCountryList::collection($this->areas),
            'content' => $this->content ?? '',
            'content_translations' => $this->getOtherTranslations('content'),
        ];
    }
}
