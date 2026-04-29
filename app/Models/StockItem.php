<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_stock_items';

    protected $guarded = [];

    public function stock()
    {
        return $this->belongsTo(Stock::class, 'stock_id', 'id');
    }

}
