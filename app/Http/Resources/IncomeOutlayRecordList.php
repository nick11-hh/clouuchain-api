<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use App\Models\AdminGroup;
use App\Models\IncomeOutlayRecord;
use App\Models\IncomeOutlayRule;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class IncomeOutlayRecordList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user->name ?? '',
            'user_uid' => $this->user->uid ?? '',
            'income_outlay_rule_id' => $this->income_outlay_rule_id,
            'income_outlay_rule_code' => $this->income_outlay_rule_code,
            'income_outlay_rule_name' => $this->rule_name,
            'serial_no' => $this->serial_no,
            'resource_type' => $this->resource_type,
            'resource_type_name' => $this->resource_type_name,
            'type' => $this->type,
            'type_name' => $this->type_name,
            'amount' => $this->amount / 100,
            'value' => $this->value,
            'enable_value' => $this->enable_valie,
            'order_sn' => $this->order_sn,
            'valid_time' => $this->valid_time,
            'valid_time_name' => (IncomeOutlayRule::getValidTimeList())[$this->valid_time] ?? '',
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'remark' => $this->remark,
            'operator' => $this->operator,
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at,
        ];
    }
}
