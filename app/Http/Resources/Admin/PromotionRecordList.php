<?php


namespace App\Http\Resources\Admin;


use Illuminate\Http\Resources\Json\JsonResource;

class PromotionRecordList extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'agent_id'          => $this->agent_id,
            'custom_id'         => $this->custom_id,
            'custom_name'       => $this->custom->custom_name,
            'order_number'      => $this->order_number,
            'order_amount'      => $this->order_amount,
            'proportion'        => $this->proportion,
            'status'            => $this->status,
            'status_name'       => $this->status_name,
            'commission_amount' => $this->commission_amount ?? '',
            'created_at'        => (string)$this->created_at,
            'updated_at'        => (string)$this->updated_at,
        ];
    }
}
