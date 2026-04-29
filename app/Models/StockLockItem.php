<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockLockItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_stock_lock_items';

    protected $guarded = [];

    public function stockLock()
    {
        return $this->belongsTo(StockLock::class, 'lock_id', 'id');
    }

    public function stockItem()
    {
        return $this->belongsTo(StockItem::class, 'stock_item_id', 'id');
    }
}
