<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExchangeRateLogsModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_exchange_rate_logs';

    const TYPE_CREATE = 0;
    const TYPE_EDIT = 1;

}
