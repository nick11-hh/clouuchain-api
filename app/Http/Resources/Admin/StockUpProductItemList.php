<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class StockUpProductItemList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'stock_up_id' => $this->stock_up_id,
            'sku' => $this->sku,
            'num' => $this->num,
            'product_quote_default_profit_rate' => $this->product_quote_default_profit_rate,
            'price' => $this->price,
            'total' => $this->total,
            'created_at' => (string)$this->created_at,
            'deleted_at' => $this->deleted_at ?? '',
        ];
    }

}
