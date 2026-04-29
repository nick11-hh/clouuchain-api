<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class GoodsAllocationDetailList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'warehouse_name' => $this->area->warehouse->warehouse_name ?? '',
            'area_number' => $this->area->number,
            'code' => $this->code,
            'status' => $this->is_locked ? 2 : ($this->used_count > 0 ? 1 : 0),
            'used_count' => $this->used_count,
            'created_at' => (string) $this->created_at,
        ];
    }
}
