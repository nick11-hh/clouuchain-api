<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchasePlanItemList extends JsonResource
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
            'id'                    => $this->id,
            'plan_id'               => $this->plan_id,
            'sn'                    => $this->sn,
            'images'                => $this->images,
            'goods_name'            => $this->goods_name,
            'spec_name'             => $this->spec_name,
            'plan_qty'              => $this->plan_qty,
            'goods_sku_id'          => $this->goods_sku_id,
            'order_sn'              => $this->order_sn,
            'order_item_id'         => $this->order_item_id,
            'status'                => $this->status,
            'plan_procurement_time' => $this->plan_procurement_time,
            'purchased_at'          => $this->purchased_at,
            'supplier_id'           => $this->supplier_id,
            'shop_id'               => $this->shop_id,
            'warehouse_id'          => $this->warehouse_id,
            'supplier'              => $this->supplier,
            'shop'                  => $this->shop,
            'warehouse'             => $this->warehouse,
            'goods_sku'             => $this->goodsSku,
            'purchased'             => $this->purchased,
            'created_at'            => (string)$this->created_at,
            'updated_at'            => (string)$this->updated_at,
        ];
    }
}
