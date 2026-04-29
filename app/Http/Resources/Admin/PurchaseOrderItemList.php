<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemList extends JsonResource
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
            'id'                => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'platform_url'      => $this->platform_url,
            'imgs'              => $this->imgs,
            'title'             => $this->title,
            'variant_title'     => $this->variant_title,
            'purchase_price'    => $this->purchase_price,
            'quantity'          => $this->quantity,
            'receive_quantity'  => $this->receive_quantity,
            'send_quantity'     => $this->send_quantity,
            'inbound_quantity'  => $this->inbound_quantity,
            'order_sn'          => $this->order_sn,
            'plan_sn'           => $this->plan_sn,
            'platform'          => $this->platform,
            'sku'               => $this->sku,
            'sku_id'            => $this->sku_id,
            'created_at'        => (string)$this->created_at,
            'updated_at'        => (string)$this->updated_at,
        ];
    }
}
