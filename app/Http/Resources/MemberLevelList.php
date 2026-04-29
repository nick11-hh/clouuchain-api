<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use App\Models\AdminGroup;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberLevelList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'growth_value' => $this->growth_value,
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at
        ] + $this->getTranslatedLocalesDiffAll()->toArray();
    }
}
