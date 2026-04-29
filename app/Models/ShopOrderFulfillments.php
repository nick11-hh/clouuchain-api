<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopOrderFulfillments extends Model
{
    use HasFactory;

    protected $table = 'dsp_shop_order_fulfillments';

    protected $guarded = [];

}
