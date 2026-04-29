<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogisticsQuoteList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'                        => $this['id'],
            'name'                      => $this['name'],
            'origin_logistics_currency' => $this['origin_logistics_currency'],
            'origin_logistics_fee'      => $this['origin_logistics_fee'],
            'expire_fee'                => $this['expire_fee'],
            'count_weight'              => $this['count_weight'],
            'reference_time'            => $this['reference_time'],
//            'delivery_min_days'         => $this['delivery_min_days'],
//            'delivery_max_days'         => $this['delivery_max_days'],
            'logistics_cost'            => $this['logistics_cost'] ?? 0,
            'logistics_profit'          => $this['logistics_profit'] ?? 0,
            'expire_fee_currency'       => $this['expire_fee_currency'] ?? '',
        ];
    }
}
