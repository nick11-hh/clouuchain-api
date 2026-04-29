<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItemPurchase extends Model
{
    use HasFactory;

    protected $table = 'dps_order_item_purchases';

    protected $guarded = [];

    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseOrdersItemsModel::class, 'purchase_item_id', 'id');
    }

    public function stockLock()
    {
        return $this->hasOne(StockLockLog::class, 'id', 'lock_id');
    }

}
