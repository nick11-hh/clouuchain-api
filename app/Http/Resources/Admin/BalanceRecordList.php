<?php

namespace App\Http\Resources\Admin;

use App\Models\BalanceRecord;
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
        $credit_line = $frozen_limit = '-';
        if ($this->source_type == 10) {
            $credit_line = $this->actual_amount / 100;
        }
        if ($this->source_type == 11) {
            $frozen_limit = $this->actual_amount / 100;
        }
        return [
            'id' => $this->id,
            'custom_id' => $this->custom_id,
            'custom' => CustomInfo::make($this->custom),
            'type' => $this->type,
            'type_name' => $this->type_name ?? '',
            'source_type' => $this->source_type,
            'source_type_name' => $this->source_type_name ?? '',
            'amount' => $this->amount / 100,
            'after_change_balance' => $this->after_change_balance / 100,
            'actual_amount' => $this->actual_amount / 100,
            'credit_line' => $credit_line,
            'frozen_limit' => $frozen_limit,
            'relation_id' => $this->relation_id,
            'order_sn' => trim(in_array($this->source_type, [BalanceRecord::SOURCE_ORDER_PAY, BalanceRecord::SOURCE_ORDER_REFUND, BalanceRecord::SOURCE_SUPPLEMENT_FEE]) ? $this->order?->order_id . ' / ' . $this->order?->name : $this->order_sn, ' / '),
            'serial_no' => $this->serial_no,
            'operate_admin_id' => $this->operate_admin_id,
            'operate_admin_name' => $this->admin ? $this->admin->name : '',
            'out_serial_no' => $this->out_serial_no,
            'remark' => $this->remark,
            'cost_breakdown' => $this->cost_breakdown,
            'created_at' => (string)$this->created_at,
            'attachment_files' => $this->attachment_files ?: []
        ];
    }
}
