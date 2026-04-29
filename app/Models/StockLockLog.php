<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockLockLog extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_stock_lock_logs';

    protected $guarded = [];

    CONST TYPE_LOCK = 1;
    CONST TYPE_UNLOCK = 2;

    CONST TYPE_LIST = [
        self::TYPE_LOCK => '锁定',
        self::TYPE_UNLOCK => '解锁',
    ];

    CONST SOURCE_ORDER_WAIT_DELIVER = 1;
    CONST SOURCE_ORDER_DELIVERED = 2;
    CONST SOURCE_ORDER_WAIT_PRINT = 3;
    CONST SOURCE_ORDER_REFUND = 4;

    CONST SOURCE_LIST = [
        self::SOURCE_ORDER_WAIT_DELIVER => '订单待发货',
        self::SOURCE_ORDER_DELIVERED => '订单发货',
        self::SOURCE_ORDER_WAIT_PRINT => '配货',
        self::SOURCE_ORDER_REFUND => '订单退款',
    ];

    public function stock()
    {
        return $this->belongsTo(Stock::class, 'stock_id', 'id');
    }

}
