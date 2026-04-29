<?php

namespace App\Models;

class ReservedOrderNumberModel extends Model
{
    protected $table = 'dsp_reserved_order_number';
    protected $fillable = ['batch', 'express_company_id', 'company_id', 'remark'];

    public function express()
    {
        return $this->belongsTo(CompanyExpressModel::class, 'express_company_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(ReservedOrderNumberItem::class, 'reserved_order_number_id', 'id');
    }
}
