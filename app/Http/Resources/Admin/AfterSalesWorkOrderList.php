<?php

namespace App\Http\Resources\Admin;

use App\Models\AfterSalesWorkOrder;
use Illuminate\Http\Resources\Json\JsonResource;

class AfterSalesWorkOrderList extends JsonResource
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
            'custom_id' => $this->custom_id,
            'custom_name' => $this->custom->custom_name ?? '',
            'work_order_id' => $this->work_order_id,
            'reference_order_id' => $this->reference_order_id ?? '',
            'desc' => $this->desc,
            'type' => $this->type,
            'type_name' => AfterSalesWorkOrder::getTypeName($this->type),
            'attachment_url' => $this->attachment_url ?? '',
            'handle_admin_id' => $this->handle_admin_id,
            'handle_admin_name' => $this->handleAdmin->name ?? '',
            'handle_result' => $this->handle_result,
            'status' => $this->status,
            'created_at' => (string) $this->created_at,
            'handle_time' => (string) $this->handle_time,
        ];
    }
}
