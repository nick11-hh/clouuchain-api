<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockChangeLogs extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_stock_change_logs';

    protected $guarded = [];

    CONST CHANGE_TYPE_INCREASE = 1;
    CONST CHANGE_TYPE_DEDUCTION = 2;
    CONST CHANGE_TYPE_ADD_IN_TRANSIT = 3;
    CONST CHANGE_TYPE_DEDUCT_IN_TRANSIT = 4;


    CONST CHANGE_TYPE_LIST = [
        self::CHANGE_TYPE_INCREASE => '增加',
        self::CHANGE_TYPE_DEDUCTION => '减少',
        self::CHANGE_TYPE_ADD_IN_TRANSIT => '增加在途库存',
        self::CHANGE_TYPE_DEDUCT_IN_TRANSIT => '减少在途库存',
    ];

    CONST SOURCE_PURCHASE = 1;
    CONST SOURCE_ORDER_DEDUCTION = 2;
    CONST SOURCE_STOCK_INVENTORY= 3;
    CONST SOURCE_INBOUND_ORDER= 4;

    CONST CHANGE_SOURCE_LIST = [
        self::SOURCE_PURCHASE => '采购单入库',
        self::SOURCE_ORDER_DEDUCTION => '订单出库',
        self::SOURCE_STOCK_INVENTORY => '库存盘点',
        self::SOURCE_INBOUND_ORDER => '入库单',
    ];

    public function warehouse()
    {
        return $this->belongsTo(WarehouseAddress::class, 'warehouse_id', 'id');
    }

    public function getSourceNameAttribute(): string
    {
        return self::CHANGE_SOURCE_LIST[$this->source] ?? '-';
    }

    public function getChangeTypeNameAttribute(): string
    {
        return self::CHANGE_TYPE_LIST[$this->change_type] ?? '-';
    }
}
