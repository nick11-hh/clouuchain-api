<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PackagePropList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'cn_name' => $this->cn_name,
            'en_name' => $this->en_name,
            'name' => $this->name,
            'color' => $this->color,
            'font_color' => $this->font_color,
            'index' => $this->index,
        ] + $this->getTranslatedLocalesDiffAll()->toArray();
    }
}
