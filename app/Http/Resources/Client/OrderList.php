<?php

namespace App\Http\Resources\Client;

use App\Http\Resources\ShopList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderList extends JsonResource
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
            'id'                            => $this->id,
            'order_id'                      => $this->order_id,
            'currency'                      => $this->currency,
            'current_total_price'           => $this->current_total_price,
            'name'                          => $this->name,
            'shop'                          => ShopList::make($this->shop),
            'line_items'                    => OrderLineItemList::collection($this->lineItems),
            'shippingAddress'               => OrderShippingAddressInfo::make($this->shippingAddress),
            'logistics_apply'               => $this->logisticsApply,
            'logistics_provider'            => $this->logistics_provider,
            'purchase_order'                => $this->purchaseOrder,
            'track_info'                    => $this->trackInfo,
            'vendor_price'                  => $this->vendor_price,
            'shipment_number'               => $this->shipment_number,
            'channel'                       => $this->channel,
            'status'                        => $this->order_status,
            'status_name'                   => $this->status_name,
            'client_status_name'            => $this->client_status_name,
            'logistics_fee'                 => $this->logistics_fee,
            'paymented_at'                  => (string)$this->paymented_at,
            'commited_at'                   => (string)$this->commited_at,
            'fulfillment_request_status'    => $this->fulfillment_request_status,
            'created_at'                    => (string)$this->created_at,
            'updated_at'                    => (string)$this->updated_at,
            'vendor_change_price'           => (string)$this->vendor_change_price,
            'custom_order_id'               => $this->custom_order_id,
            'other_supplement_price'        => $this->other_supplement_price,
            'order_one_price'               => $this->order_one_price,
            'favourable_price'              => $this->favourable_price ?? 0,
            'sku_logistics_fee'             => $this->sku_logistics_fee ?? 0,
            'original_amount'               => $this->original_amount ?? 0,
            'total_amount'                  => $this->total_amount ?? 0,
            'warehouse_name'                => $this->warehouse->warehouse_name ?? '', //仓库名称 备货订单才存在
            'express_line'                  => $this->expressLine,
            'financial_status'              => $this->financial_status, //财务状态 0-未支付 1-已支付 2-补收费用 3-部分退款 4-全额退款
            'express_order'                 => $this->expressOrders[0] ?? [],
            'supplement_price'              => $this->supplement_price ?? 0,
            'supplement_charge_remark'      => $this->supplement_charge_remark ?? '',
            'refund_price'                  => $this->refund_price ?? 0,
            'charge_type_id'                => $this->charge_type_id ?? 0,
            'charge_type_data'              => $this->chargeType,
            'packages'                      => $this->packages
        ];
    }
}
