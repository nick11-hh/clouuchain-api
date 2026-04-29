<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboundShopOrderRelate extends Model
{
    use HasFactory;

    protected $table = 'dsp_outbound_shop_order_relates';

    protected $guarded = [];
}
