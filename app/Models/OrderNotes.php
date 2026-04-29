<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderNotes extends Model
{
    use SoftDeletes;

    protected $table = 'dsp_shop_order_notes';
    
    protected $guarded = [];
}
