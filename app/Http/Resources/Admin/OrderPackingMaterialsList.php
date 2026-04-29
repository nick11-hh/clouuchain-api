<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderPackingMaterialsList extends JsonResource
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
            'id'            => $this->id,
            'order_id'      => $this->order_id,
            'goods_id'      => $this->goods_id,
            'goods_sku_id'  => $this->goods_sku_id,
            'name'          => $this->name,
            'quantity'      => $this->quantity,
            'sku'           => $this->sku,
            'spec_name'     => $this->spec_name,
            'images'        => $this->images,
            'operator_id'   => $this->operator_id,
            'operator_name' => $this->admin->name ?? '',
            'created_at'    => (string)$this->created_at,
            'updated_at'    => (string)$this->updated_at,
        ];
    }
}
