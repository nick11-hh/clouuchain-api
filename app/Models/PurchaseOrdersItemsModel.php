<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrdersItemsModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_purchase_orders_items';

    protected $casts = [
        'imgs' => 'array',
        'order_sn' => 'array',
        'plan_sn' => 'array'
    ];

    public function order()
    {
        return $this->belongsTo(PurchaseOrdersModel::class, 'purchase_order_id', 'id');
    }

    public function shopOrders()
    {
        return $this->belongsToMany(
            Order::class,
            'dps_order_item_purchases',
            'purchase_item_id',
            'order_id'
        );
    }

    public function orderItemPurchase()
    {
        return $this->hasMany(OrderItemPurchase::class, 'purchase_item_id', 'id');
    }

    public static function init($purchase_order_id, $data)
    {
        return [
            'purchase_order_id' => $purchase_order_id,
            'platform_url'      => $data['url'] ?? '',
            'imgs'              => json_encode($data['images']),
            'variant_title'     => $data['spec_name'] ?? '',
            'title'             => $data['goods_name'],
            'quantity'          => $data['plan_qty'],
            'platform'          => $data['platform'] ?? 1688,
            'sku'               => $data['goods_sku']['sku_id'] ?? null,
            'offerId'           => $data['offerId'] ?? null,
            'specId'            => $data['specId'] ?? null,
            'goods_id'          => $data['goods_id'] ?? 0,
            'sku_id'            => $data['goods_sku_id'] ?? 0,
            'purchase_price'    => $data['purchase_price'] ?? 0,
            'created_at'        => now(),
            'updated_at'        => now()
        ];
    }
}
