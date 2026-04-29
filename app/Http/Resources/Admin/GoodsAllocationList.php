<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class GoodsAllocationList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'area_id' => $this->area_id,
            'number' => $this->area->number,
            'column' => $this->column,
            'row' => $this->row,
            'code' => $this->code,
            'max_count' => $this->max_count,
            'used_count' => $this->used_count,
            'is_locked' => $this->is_locked,
            'created_at' => (string) $this->created_at,
        ];
    }
}
