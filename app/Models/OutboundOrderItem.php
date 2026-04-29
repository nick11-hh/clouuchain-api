<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OutboundOrderItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_outbound_order_items';

    protected $guarded = [];

    protected $casts = [];

    public function orderStocks() {
        return $this->hasManyThrough(
            OrderItemStock::class,
            OutboundItemOrderStockRelate::class,
            'outbound_item_id',
            'id',
            'id',
            'order_item_stock_id'
        );
    }

    public function stock()
    {
        return $this->hasOne(Stock::class, 'id', 'stock_id');
    }

    public static function init($outboundId, $params)
    {
        return [
            'outbound_id' => $outboundId,
            'stock_id' => $params['stock_id'],
            'goods_name' => $params['goods_name'],
            'sku' => $params['sku'],
            'spec_name' => $params['spec_name'],
            'sku_image' => $params['sku_image'],
            'quantity' => $params['quantity'],
            'price' => $params['price'] ?? 0,
            'remark' => $params['remark'] ?? '',
        ];
    }
}
