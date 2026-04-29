<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class PackageItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_package_items';

    protected $guarded = [];

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id', 'id');
    }

    public function lineItem()
    {
        return $this->belongsTo(OrderLineItem::class, 'shop_order_item_id', 'id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'shop_order_id', 'id');
    }

}
