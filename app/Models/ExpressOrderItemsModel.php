<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class ExpressOrderItemsModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_express_order_items';

    protected $guarded = [];

    protected $casts = [
        'attributes' => 'array',
    ];

    public static function init($params): array
    {
        return [
            'express_order_id'   => $params['express_order_id'] ?? 0, //物流订单ID
            'cn_name'            => $params['cn_name'] ?? '', //报关中文名
            'en_name'            => $params['en_name'] ?? '', //报关英文名
            'unit_price'         => $params['unit_price'] ?? 0, //报关单价(USD)
            'weight'             => $params['weight'] ?? 0, //报关重量(g)
            'hs_code'            => $params['hs_code'] ?? '', //海关编码
            'attributes'         => $params['attributes'] ?? [], //物品属性
            'quantity'           => $params['quantity'] ?? '', //数量
            'material'           => $params['material'] ?? '', //材质
            'use_to'             => $params['use_to'] ?? '', //用途
            'sku'                => $params['sku'] ?? '', //sku
            'shop_order_id'      => $params['shop_order_id'] ?? '', //订单主键
            'shop_order_item_id' => $params['shop_order_item_id'] ?? '', //订单商品主键
        ];
    }

}
