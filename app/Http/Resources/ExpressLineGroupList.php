<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineGroupList extends JsonResource
{
    public function toArray($request)
    {
        return [
                'id' => $this->id,
                'name' => $this->name,
                'express_lines_count' => $this->express_lines_count,
                'enabled' => $this->enabled,
                'only_for_group' => $this->only_for_group ?? 0,
                'only_for_stg' => $this->only_for_stg,
            ] + $this->getTranslatedLocalesDiffAll()->toArray();
    }
}
