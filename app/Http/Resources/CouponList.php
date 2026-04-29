<?php

/**
 * @Author: h9471
 * @Created: 2019/10/25 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CouponList extends JsonResource
{
    public function toArray($request)
    {
        return [
                'id' => $this->id,
                'scope' => $this->scope,
                'usable_lines' => CommonJsonNameList::collection($this->usableLines),
                'usable_countries' => CommonJsonNameList::collection($this->usableCountries),
                'name' => $this->name,
                'status' => $this->couponStatus,
                'type' => $this->typeName ?? '',
                'amount' => $this->amount / 100,
                'threshold' => $this->threshold / 100,
                'enabled' => $this->enabled,
                'total_count' => $this->total_count,
                'used_count' => $this->used_count,
                'unused_count' => $this->unused_count,
                'expired_count' => $this->expired_count,
                'invalid_count' =>  $this->invalid_count,
                'share_qr_code' => $this->share_qr_code ?? '',
                'share_status' => $this->shareStatus,
                'discount_type' => $this->discount_type,
                'weight' => $this->weight / 1000,
                'min_weight' => $this->min_weight / 1000,
                'max_weight' => $this->max_weight / 1000,
                'remark' => $this->remark ?? '',
                'days' => $this->days ?? 0,
                'ceiling' => $this->ceiling ?? 0,
                'discount_method' => $this->discount_method ?? 0,
                'effected_at' => (string) $this->effected_at,
                'expired_at' => (string) $this->expired_at,
                'created_at' => (string) $this->created_at,
            ] + $this->getTranslatedLocalesDiffAll()->toArray();
    }
}
