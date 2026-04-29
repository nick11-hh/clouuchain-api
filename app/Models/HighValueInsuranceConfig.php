<?php

namespace App\Models;

use App\Models\Traits\Basis;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HighValueInsuranceConfig extends Model
{
    use Basis,
        HasFactory;

    protected $table = 'dsp_high_value_insurance_config';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    /**
     * 工单类型
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function workOrderType()
    {
        return $this->belongsTo(WorkOrderType::class, 'work_order_type_id', 'id');
    }
}
