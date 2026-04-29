<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class OutboundOrderInfo extends JsonResource
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
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse->warehouse_name ?? '',
            'custom_id' => $this->custom_id,
            'custom_name' => $this->custom->custom_name ?? '',
            'outbound_sn' => $this->outbound_sn,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'type' => $this->type,
            'type_name' => $this->type_name,
            'sale_platform' => $this->sale_platform,
            'sale_platform_name' => $this->sale_platform_name,
            'logistics_provider' => $this->logistics_provider,
            'tracking_number' => $this->tracking_number,
            'shipment_pdf' => $this->shipment_pdf,
            'remark' => $this->remark ?? '',
            'created_at' => (string)$this->created_at,
            'add_picking_time' => (string)$this->add_picking_time,
            'picking_time' => (string)$this->picking_time,
            'packaged_time' => (string)$this->packaged_time,
            'outbound_time' => (string)$this->outbound_time,
            'cancel_time' => (string)$this->cancel_time,
            'long' => $this->long,
            'width' => $this->width,
            'height' => $this->height,
            'weight' => $this->weight,
            'items' => OutboundOrderItemList::collection($this->items),
            'orders' => OrderList::collection($this->orders),
            'address' => $this->address
        ];
    }
}
