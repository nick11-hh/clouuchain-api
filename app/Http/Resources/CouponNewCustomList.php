<?php

/**
 * @Author: h9471
 * @Created: 2019/10/25 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CouponNewCustomList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->userCoupons?->first()?->user_id ?? 0,
                'name' => $this->userCoupons?->first()?->user->name ?? '',
            ],
            'scope' => $this->scope,
            'usable_lines' => CommonJsonNameList::collection($this->usableLines),
            'name' => $this->name,
            'status' => $this->couponStatus,
            'amount' => $this->amount / 100,
            'threshold' => $this->threshold / 100,
            'enabled' => $this->enabled,
            'used_count' => $this->used_count,
            'invalid_count' => $this->invalid_count,
            'min_weight' => $this->min_weight / 1000,
            'max_weight' => $this->max_weight / 1000,
            'remark' => $this->remark ?? '',
            'days' => $this->days ?? 0,
            'effected_at' => (string)$this->effected_at,
            'expired_at' => (string)$this->expired_at,
            'created_at' => (string)$this->created_at,
        ];
    }
}
