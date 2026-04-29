<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ShopList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockOrderList extends JsonResource
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
            'id'                     => $this->id,
            'order_id'               => $this->order_id,
            'currency'               => $this->currency,
            'current_total_price'    => $this->current_total_price,
            'name'                   => $this->name,
            'line_items'             => OrderLineItemList::collection($this->lineItems),
            'logistics_apply'        => $this->logisticsApply,
            'logistics_apply_change' => $this->logisticsApplyChange,
            'purchase_order'         => $this->purchaseOrder,
            'track_info'             => $this->trackInfo,
            'vendor_price'           => $this->vendor_price,
            'channel'                => $this->channel,
            'channel_change'         => $this->channelChange,
            'status'                 => $this->order_status,
            'status_name'            => $this->status_name,
            'logistics_fee'          => $this->logistics_fee,
            'is_change'              => $this->is_change,
            'quantity'               => $this->quantity ?? 0,
            'arrive_quantity'        => $this->arrive_quantity ?? 0,
            'arrived'                => $this->arrived ?? 0,
            'logistics_provider'     => $this->logistics_provider,
            'express_line_id'        => $this->express_line_id,
            'is_hand_customs'        => $this->is_hand_customs,
            'handCustoms'            => $this->handCustoms,
            'paymented_at'           => (string)$this->paymented_at,
            'commited_at'            => (string)$this->commited_at,
            'created_at'             => (string)$this->created_at,
            'updated_at'             => (string)$this->updated_at,
            'warehouse_id'           => $this->warehouse_id ?? 0,
            'warehouse_name'         => $this->warehouse->warehouse_name ?? '',
            'custom_name'            => $this->custom->custom_name ?? '',
        ];
    }
}
