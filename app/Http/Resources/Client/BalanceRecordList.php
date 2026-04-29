<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class BalanceRecordList extends JsonResource
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
            'type' => $this->type,
            'type_name' => $this->type_name ?? '',
            'source_type' => $this->source_type,
            'source_type_name' => $this->source_type_name ?? '',
            'amount' => $this->amount / 100,
            'after_change_amount' => $this->after_change_balance / 100,
            'order_sn' => $this->order_sn,
            'serial_no' => $this->serial_no,
            'out_serial_no' => $this->out_serial_no,
            'remark' => $this->cost_breakdown,
            'created_at' => (string)$this->created_at,
        ];
    }
}
