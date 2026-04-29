<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'                     => $this->id,
            'order_sn'               => $this->order_sn,
            'shop_order_id'          => $this->shop_order_id,
            'shop_id'                => $this->shop_id,
            'platform_sn'            => $this->platform_sn,
            'shipment_number'        => $this->shipment_number,
            'status'                 => $this->status,
            'status_name'            => $this->status_name,
            'created_at'             => (string)$this->created_at,
            'updated_at'             => (string)$this->updated_at,
            'skus'                   => PurchaseOrderItemList::collection($this->skus),
            'shop'                   => $this->shop,
            'order'                  => $this->shopOrder,
            'supplier_id'            => $this->provider_id,
            'supplier_name'          => $this->supplier->supplier_name ?? '',
            'supplier_type'          => $this->supplier->type ?? '',
            'supplier_type_name'     => $this->supplier->type_name ?? '',
            'warehouse_id'           => $this->warehouse_id,
            'warehouse_name'         => $this->warehouse->warehouse_name ?? '',
            'purchase_user_id'       => $this->purchase_user_id,
            'remark'                 => $this->remark,
            'transaction_method'     => $this->transaction_method,
            'purchase_account_id'    => $this->purchase_account_id,
            'expect_time'            => $this->expect_time,
            'order_time'             => (string)$this->order_time,
            'other_fees'             => $this->other_fees,
            'related_orders'         => $this->related_orders,
            'freight'                => $this->freight,
            'message'                => $this->message,
            'plans'                  => $this->plans ?? '',
            'delivered_time'         => $this->delivered_time ?? '',
            'pay_time'               => $this->pay_time ?? '',
            'logistics_company_name' => $this->logistics_company_name ?? '',

        ];
    }
}
