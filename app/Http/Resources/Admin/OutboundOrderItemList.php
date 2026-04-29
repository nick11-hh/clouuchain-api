<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class OutboundOrderItemList extends JsonResource
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
            'outbound_id' => $this->outbound_id,
            'goods_name' => $this->goods_name,
            'spec_name' => $this->spec_name,
            'sku_image' => $this->sku_image,
            'sku' => $this->sku,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'picking_quantity' => $this->picking_quantity,
            'remark' => $this->remark,
            'stock_owner' => $this->stock->customer->custom_name ?? '本企业',
        ];
    }
}
