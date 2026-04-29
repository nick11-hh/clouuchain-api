<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class StockUpPurchaseItemList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'stock_up_id' => $this->stock_up_id,
            'order_num' => $this->order_num,
            'sku' => $this->sku,
            'num' => $this->num,
            'total' => $this->total,
            'warehouse' => $this->warehouse,
            'created_at' => (string)$this->created_at,
            'deleted_at' => $this->deleted_at ?? '',
        ];
    }
}
