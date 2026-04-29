<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CardCouponList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->coupon->name,
            'code' => $this->coupon_code,
            'discount_type' => $this->coupon->discount_type,
            'amount' => $this->coupon->amount / 100,
            'threshold' => $this->coupon->threshold / 100,
            'weight' => $this->coupon->weight / 1000,
            'status' => $this->status,
            'usable' => (bool) $this->can_use,
            'used_at' => $this->used_at ?? '',
            'created_at' => (string) $this->created_at,
        ];
    }
}
