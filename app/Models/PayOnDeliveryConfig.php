<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\LikeScope;

class PayOnDeliveryConfig extends Model
{
    use Basis, LikeScope;

    protected $table = 'dsp_pay_on_delivery_configs';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];
}
