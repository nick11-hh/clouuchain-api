<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderLogs extends JsonResource
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
            'purchase_id'            => $this->purchase_id,
            'operator_type'          => $this->operator_type,
            'content'                => $this->content,
            'operator_id'            => $this->operator_id,
            'operator_name'          => $this->admin->name ?? '',
            'created_at'             => (string)$this->created_at,
            'updated_at'             => (string)$this->updated_at,
        ];
    }
}
