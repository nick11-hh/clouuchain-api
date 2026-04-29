<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderQuoteInfo extends JsonResource
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
            'channel_list'               => LogisticsQuoteList::collection($this['channel_list']),
            'goods_price_detail'         => OrderItemQuoteList::collection($this['goods_price_detail']),
            'express_line_id'            => $this['express_line_id'],
            'favourable_compute_price'   => $this['favourable_compute_price'],
            'favourable_discount'        => $this['favourable_discount'],
            'favourable_price'           => $this['favourable_price'],
            'goods_once_price'           => $this['goods_once_price'],
            'goods_price'                => $this['goods_price'],
            'logistics_compute_fee'      => $this['logistics_compute_fee'],
            'logistics_fee'              => $this['logistics_fee'],
            'logistics_provider'         => $this['logistics_provider'],
            'logistics_provider_code'    => $this['logistics_provider_code'],
            'logistics_profit'           => $this['logistics_profit'],
            'message'                    => $this['message'],
            'origin_logistics_fee'       => $this['origin_logistics_fee'],
            'other_supplement_price'     => $this['other_supplement_price'],
            'freight_quote_calculate_method' => $this['freight_quote_calculate_method'],
            'freight_quote_amount_type'  => $this['freight_quote_amount_type'],
            'charge_type_id'             => $this['charge_type_id'],
            'total_price'                => $this['total_price'],
        ];
    }
}
