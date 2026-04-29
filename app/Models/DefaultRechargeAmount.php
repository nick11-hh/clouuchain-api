<?php

namespace App\Models;

use App\Models\Traits\Basis;

class DefaultRechargeAmount extends Model
{
    use Basis;

    protected $table = 'dsp_default_recharge_amount';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];
}
