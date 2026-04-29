<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomsQuoteConfigResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'product_quote_default_profit_rate' => $this->product_quote_default_profit_rate,
            'product_quote_review_profit_rate' => $this->product_quote_review_profit_rate,
            'product_quote_default_fixed_amount' => $this->product_quote_default_fixed_amount,
            'freight_quote_default_profit_rate' => $this->freight_quote_default_profit_rate,
            'freight_quote_review_profit_rate' => $this->freight_quote_review_profit_rate,
            'freight_quote_default_fixed_amount' => $this->freight_quote_default_fixed_amount,
        ];
    }
}
