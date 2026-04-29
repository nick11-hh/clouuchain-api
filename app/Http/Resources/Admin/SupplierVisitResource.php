<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierVisitResource extends JsonResource
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
            'supplier_id' => $this->supplier_id,
            'supplier_name' => $this->supplier->supplier_name ?? '',
            'visit_date_start' => date('Y-m-d', strtotime((string)$this->visit_date_start)),
            'visit_date_end' => date('Y-m-d', strtotime((string)$this->visit_date_end)),
            'product' => $this->product,
            'key_results' => $this->key_results,
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at,
        ];
    }
}
