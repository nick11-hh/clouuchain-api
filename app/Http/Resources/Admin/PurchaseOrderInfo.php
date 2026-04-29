<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderInfo extends JsonResource
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
            'id'              => $this->id,
            'warehouse_id'    => $this->warehouse_id,
            'warehouse_name'    => $this->warehouse->warehouse_name ?? '',
            'order_sn'        => $this->order_sn,
            'shop_order_id'   => $this->shop_order_id,
            'shop_id'         => $this->shop_id,
            'platform_sn'     => $this->platform_sn,
            'shipment_number' => $this->shipment_number,
            'status'          => $this->status,
            'status_name'     => $this->status_name,
            'created_at'      => (string)$this->created_at,
            'updated_at'      => (string)$this->updated_at,
            'skus'            => PurchaseOrderItemList::collection($this->skus),
            'quantity'        => $this->quantity,
            'inbound_quantity'=> $this->inbound_quantity,
        ];
    }
}
