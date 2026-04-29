<?php

/**
 * @Author: h9471
 * @Created: 2019/10/25 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserCouponList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'user_id' => $this->user_id,
            'coupon' => $this->coupon->name,
            'amount' => $this->coupon->amount / 100,
            'coupon_code' => $this->coupon_code,
            'used_at' => (string) $this->used_at,
            'order_number' => $this->order_number,
            'order_amount' => $this->order_amount / 100,
            'paid_at' => (string) $this->paid_at,
            'effected_at' => (string) $this->effected_at,
            'expired_at' => (string) $this->expired_at,
            'created_at' => (string) $this->created_at,
        ];
    }
}
