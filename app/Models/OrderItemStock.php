<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItemStock extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_order_item_stocks';

    protected $guarded = [];

    protected $casts = [];

    public function stockItem()
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id', 'id');
    }

}
