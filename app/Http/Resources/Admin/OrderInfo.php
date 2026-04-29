<?php

namespace App\Http\Resources\Admin;

use App\Models\Order;
use App\Http\Resources\ShopList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderInfo extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        #支付过的订单这个total_price会变成0，重新计算一下
        // if(floatval($this->total_price) <= 0){

            //物流费用 开启商品一口价 物流费用为sku的物流总报价
            $logisticsFee = $this->order_one_price === Order::ONE_PRICE_OPEN ? $this->sku_logistics_fee : $this->logistics_fee;

            //订单报价总计:商品总报价+实际物流报价/sku物流总报价-优惠金额+其他补价（非商品一口价）
            $this->total_price = $this->vendor_price + $logisticsFee - $this->favourable_price + $this->other_supplement_price;
        // }

        return [
            'id'                       => $this->id,
            'order_id'                 => $this->order_id,
            'currency'                 => $this->currency,
            'current_total_price'      => $this->current_total_price,
            'name'                     => $this->name,
            'shop'                     => ShopList::make($this->shop),
            'line_items'               => OrderLineItemList::collection($this->lineItems),
            'shippingAddress'          => OrderShippingAddressInfo::make($this->shippingAddress),
            'logistics_apply'          => $this->logisticsApply,
            'logistics_apply_change'   => $this->logisticsApplyChange,
            'purchase_order'           => $this->purchaseOrder,
            'track_info'               => $this->trackInfo,
            'vendor_price'             => $this->order_one_price === Order::ONE_PRICE_OPEN ? ($this->vendor_price + $logisticsFee) : $this->vendor_price,
            'channel'                  => $this->channel,
            'channel_change'           => $this->channelChange,
            'status'                   => $this->order_status,
            'status_name'              => $this->status_name,
            'logistics_fee'            => $this->logistics_fee,
            'is_change'                => $this->is_change,
            'quantity'                 => $this->quantity ?? 0,
            'arrive_quantity'          => $this->arrive_quantity ?? 0,
            'arrived'                  => $this->arrived ?? 0,
            'logistics_provider'       => $this->logistics_provider,
            'express_line_id'          => $this->express_line_id,
            'express_line_name'        => $this->expressLine ? $this->expressLine->cn_name : '',
            'is_hand_customs'          => $this->is_hand_customs,
            'handCustoms'              => $this->handCustoms,
            'paymented_at'             => (string)$this->paymented_at,
            'commited_at'              => (string)$this->commited_at,
            'created_at'               => (string)$this->created_at,
            'updated_at'               => (string)$this->updated_at,
            'logs'                     => $this->logs,
            'customer_id'              => $this->customer_id ?? 0,
            'packing_materials'        => OrderPackingMaterialsList::collection($this->packingMaterials),
            'custom_order_id'          => $this->custom_order_id,
            'staff_id'                 => $this->staff_id,
            'staff_name'               => $this->staff ? $this->staff->name : '',
            'other_supplement_price'   => (float)$this->other_supplement_price,
            'favourable_price'         => (float)$this->favourable_price,
            'logistics_compute_fee'    => (float)$this->logistics_compute_fee,
            'favourable_compute_price' => (float)$this->favourable_compute_price,
            'total_price'              => number_format($this->total_price, 2, '.', ''),
            'use_customer_stock'       => (int)$this->use_customer_stock,
            'charge_type_name'         => $this->chargeType ? $this->chargeType->name : '', //补收类型数据
            'platform_status'          => $this->platform_status,
            'platform_status_name'     => $this->platform_status_name,
            'order_one_price'          => $this->order_one_price,
            'freight_quote_calculate_method' => (int)$this->freight_quote_calculate_method,
            'logistics_cost'           => $this->logistics_cost,
            'logistics_profit'         => $this->logistics_profit,
            'remote_type'              => $this->remote_type,
        ];
    }
}
