<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class LogisticsChannelModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_logistics_channel';

    const DISABLE = 0;
    const ENABLE = 1;

    public function expressCompanies()
    {
        return $this->belongsTo(CompanyExpressModel::class, 'express_companies_id', 'id');
    }
}
