<?php

/**
 * @Author: h9471
 * @Created: 2019/10/25 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CouponCodeList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'remark' => $this->remark ?? '',
            'status' => $this->status,
            'total_count' => $this->total_count,
            'used_count' => $this->used_count,
            'received_count' => $this->received_count,
            'each_count' => $this->each_count,
            'created_at' => (string)$this->created_at,
        ];
    }
}
