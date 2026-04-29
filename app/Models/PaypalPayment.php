<?php

namespace App\Models;

use App\Models\Traits\Basis;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaypalPayment extends Model
{
    use Basis, HasFactory;

    protected $table = 'dsp_paypal_payment';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];
}
