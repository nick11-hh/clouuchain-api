<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemQuoteList extends JsonResource
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
            'line_item_id'       => $this['line_item_id'],
            'profit_price'       => $this['profit_price'] ?? 0,
            'purchase_price'     => $this['purchase_price'] ?? 0,
            'quantity'           => $this['quantity'] ?? 1,
            'quote_price'        => $this['quote_price'] ?? 0,
            'unit_price'         => $this['unit_price'] ?? 0,
            'use_customer_stock' => $this['use_customer_stock'] ?? 0,
            'variant_id'         => $this['variant_id'] ?? '',
            'has_quote'          => $this['has_quote'] ?? 0,
        ];
    }
}
