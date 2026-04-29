<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItemMapping extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dps_order_item_mappings';

    protected $guarded = [];

    public function goodsSku()
    {
        return $this->hasOne(GoodsSku::class, 'id', 'goods_sku_id');
    }

    public function orderLineItem()
    {
        return $this->hasOne(OrderLineItem::class, 'variant_id', 'platform_variant_id');
    }

    public function stock()
    {
        return $this->hasOne(Stock::class, 'sku_id', 'goods_sku_id');
    }

}
