<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class OrderLineItem extends Model
{
    protected $table = 'dsp_shop_order_line_items';
    protected $casts = [
        'imgs' => 'array',
        'properties' => 'array',
    ];

    public const ADD_TYPE_ORDER_ASSOCIATION_AUTO_ADD = 1;//订单关联自动添加

    public const ADD_TYPE_MANUALLY_ADD = 2; //手动添加manually


    public function mapping()
    {
        return $this->hasOne(OrderItemMapping::class, 'platform_variant_id', 'variant_id');
    }

    public function purchaseItems()
    {
        return $this->hasMany(OrderItemPurchase::class, 'order_item_id', 'id');
    }

    public function stock()
    {
        return $this->hasOne(OrderItemStock::class, 'order_item_id', 'id');
        // return $this->hasOne(Stock::class, 'sku_id', 'goods_sku_id');
    }

    public function stockItems()
    {
        return $this->hasMany(OrderItemStock::class, 'order_item_id', 'id');
    }

    public function shopOrder()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function declaration()
    {
        return $this->hasOne(OrderDeclarationModel::class, 'order_item_id', 'id');
    }

    public function mappingGoodsSku()
    {
        return $this->hasOneThrough(GoodsSku::class, OrderItemMapping::class, 'platform_variant_id', 'id', 'variant_id', 'goods_sku_id');
    }

    public function packageItems()
    {
        return $this->hasMany(PackageItem::class, 'shop_order_item_id', 'id');
    }

    public static function init($order_id, $data)
    {
        return [
            'order_id'                     => $order_id,
            'name'                         => $data['name'],
            'price'                        => $data['price'],
            'product_id'                   => $data['product_id'] ?? 0,
            'quantity'                     => $data['quantity'],
            'sku'                          => $data['sku'],
            'title'                        => $data['title'] ?? '',
            'total_discount'               => $data['total_discount'] ?? 0,
            'variant_id'                   => empty($data['variant_id']) ? 0 : $data['variant_id'],
            'variant_title'                => $data['variant_title'] ?? '',
            'line_item_id'                 => $data['line_item_id'] ?? 0,
            'imgs'                         => $data['imgs'] ?? null,
            'product_url'                  => $data['product_url'] ?? '',
            'properties'                   => $data['properties'] ?? [],
        ];
    }

    /**
     * 本地品初始化数据
     * @param $orderId
     * @param $data
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/17 17:49
     */
    public static function initByLocalProduct($orderId, $data)
    {
        return [
            'order_id'                     => $orderId,
            'name'                         => $data['goods_name'],
            'price'                        => $data['quote_price'],
            'product_id'                   => $data['goods_id'],
            'quantity'                     => $data['quantity'],
            'sku'                          => $data['sku_id'],
            'title'                        => $data['goods_name'],
            'total_discount'               => $data['total_discount'] ?? 0,
            'variant_id'                   => $data['system_sku'],
            'variant_title'                => $data['spec_name'],
            'line_item_id'                 => $data['sku_id'],
            'imgs'                         => $data['images'] ?? null,
            'add_type'                     => $data['add_type'] ?? 1,
        ];
    }
}

