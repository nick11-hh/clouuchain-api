<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StringTranslationList extends JsonResource
{
    public function toArray($request)
    {
        return parent::toArray($request);
    }
}
