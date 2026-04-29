<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineServiceInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'remark' => $this->remark,
            'type' => $this->type,
            'is_forced' => $this->is_forced,
            'prices' => ExpressLineRegionServicePriceList::collection($this->prices),
            'name_translations' => $this->getOtherTranslations('name'),
            'remark_translations' => $this->getOtherTranslations('remark'),
        ];
    }
}
