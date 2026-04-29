<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservedOrderNumberItem extends Model
{
    protected $table = 'dsp_reserved_order_number_item';
    protected $fillable = ['reserved_order_number_id', 'order_sn', 'express_company_id', 'is_used', 'is_invalid', 'company_id'];

    const IS_INVALID = 1;       // 已作废
    const STATUS_NORMAL = 0;    // 正常状态

    const IS_USED = 1;          // 已使用
    const UNUSED = 0;           // 未使用

    public function express()
    {
        return $this->belongsTo(ExpressCompany::class, 'express_company_id', 'id');
    }

    /**
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_sn', 'logistics_sn');
    }
}
