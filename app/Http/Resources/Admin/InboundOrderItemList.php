<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class InboundOrderItemList extends JsonResource
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
            'inbound_id' => $this->inbound_id,
            'goods_name' => $this->goods_name,
            'goods_sku' => $this->goods_sku,
            'spec_name' => $this->spec_name,
            'sku_image' => $this->sku_image,
            'quantity' => $this->quantity,
            'sign_quantity' => $this->sign_quantity,
            'inbound_quantity' => $this->inbound_quantity,
            'remark' => $this->remark,
            'length' => $this->length ?: $this->goodsSku->length,
            'width' => $this->width ?: $this->goodsSku->width,
            'height' => $this->height ?: $this->goodsSku->height,
            'weight' => $this->weight ?: $this->goodsSku->weight,
            'goods_sku_id' => $this->goods_sku_id,
        ];
    }
}
