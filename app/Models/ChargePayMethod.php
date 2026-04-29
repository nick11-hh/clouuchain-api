<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargePayMethod extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_charge_pay_methods';

    protected $guarded = [];

}
