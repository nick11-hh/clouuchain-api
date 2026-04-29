<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CountryExpressList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->iName,
            'cn_name' => $this->iName,
            'index' => $this->index,
            'timezone' => $this->timezone,
        ];
    }
}
