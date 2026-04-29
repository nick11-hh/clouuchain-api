<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockLock extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_stock_locks';

    protected $guarded = [];

    CONST SOURCE_ORDER_WAIT_DELIVER = 1;
    CONST SOURCE_ORDER_DELIVERED = 2;
    CONST SOURCE_ORDER_WAIT_PRINT = 3;
    CONST SOURCE_ORDER_REFUND = 4;

    CONST SOURCE_LIST = [
        self::SOURCE_ORDER_WAIT_DELIVER => '订单待发货',
        self::SOURCE_ORDER_DELIVERED => '订单发货',
        self::SOURCE_ORDER_WAIT_PRINT => '待打单',
        self::SOURCE_ORDER_REFUND => '订单退款',
    ];

    public function items()
    {
        return $this->hasMany(StockLockItem::class, 'lock_id', 'id');
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class, 'stock_id', 'id');
    }

}
