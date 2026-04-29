<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class GoodsAllocationAreaList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'number' => $this->number,
            'column' => $this->column,
            'row' => $this->row,
            'counts' => $this->type == 1 ? $this->allocations->count() : $this->counts,
            'max_count' => $this->allocations->first()->max_count ?? 1,
            'created_at' => (string) $this->created_at,
            'index' => $this->index,
            'status' => $this->is_locked,
            'type' => $this->type,
            'packages_count' => $this->allocations->sum('used_count'),
            'use_type' => $this->use_type ?? 1,
        ];
    }
}
