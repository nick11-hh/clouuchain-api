<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutboundItemOrderStockRelate extends Model
{
    use HasFactory;

    protected $table = 'dsp_outbound_item_order_stock_relates';

    protected $guarded = [];
}
