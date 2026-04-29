<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class PackageList extends JsonResource
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
            'id'                     => $this->id,
            'package_sn'             => $this->package_sn,
            'express_companies_id'   => $this->express_companies_id,
            'express_companies_code' => $this->express_companies_code,
            'express_companies_name' => $this->expressCompany->name ?? '-',
            'express_line_code'      => $this->express_line_code,
            'status'                 => $this->status,
            'logistics_status'       => $this->logistics_status,
            'stock_status'           => $this->stock_status,
            'split_merge_status'     => $this->split_merge_status,
            'weight'                 => $this->weight,
            'length'                 => $this->length,
            'width'                  => $this->width,
            'height'                 => $this->height,
            'items'                  => $this->items,
            'logistics_apply'        => $this->logisticsApply,
            'orders'                 => $this->orders,
            'package_address'        => $this->packageAddress,
            'created_at'             => (string)$this->created_at,
            'updated_at'             => (string)$this->updated_at,
        ];
    }
}
