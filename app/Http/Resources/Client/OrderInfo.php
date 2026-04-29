<?php

namespace App\Http\Resources\Client;

use App\Http\Resources\ShopList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderInfo extends JsonResource
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
            'id'                         => $this->id,
            'order_id'                   => $this->order_id,
            'currency'                   => $this->currency,
            'current_total_price'        => $this->current_total_price,
            'name'                       => $this->name,
            'shop'                       => ShopList::make($this->shop),
            'line_items'                 => OrderLineItemList::collection($this->lineItems),
            'shippingAddress'            => OrderShippingAddressInfo::make($this->shippingAddress),
            'logistics_apply'            => $this->logisticsApply,
            'logistics_provider'         => $this->logistics_provider,
            'purchase_order'             => $this->purchaseOrder,
            'track_info'                 => $this->trackInfo,
            'vendor_price'               => $this->vendor_price,
            'shipment_number'            => $this->shipment_number,
            'channel'                    => $this->channel,
            'expressLine'                => $this->expressLine,
            'status'                     => $this->order_status,
            'status_name'                => $this->status_name,
            'logistics_fee'              => $this->logistics_fee,
            'paymented_at'               => (string)$this->paymented_at,
            'commited_at'                => (string)$this->commited_at,
            'fulfillment_request_status' => $this->fulfillment_request_status,
            'created_at'                 => (string)$this->created_at,
            'updated_at'                 => (string)$this->updated_at,
            'order_type'                 => $this->order_type,
            'warehouse_id'               => $this->warehouse_id,
            'use_customer_stock'         => $this->use_customer_stock,
        ];
    }
}
