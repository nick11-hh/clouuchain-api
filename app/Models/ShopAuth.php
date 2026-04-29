<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopAuth extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'dsp_shop_auths';

    protected $casts = [
        'origin_data' => 'array'
    ];

    protected $guarded = [];
}
