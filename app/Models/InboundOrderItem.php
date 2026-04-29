<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InboundOrderItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_inbound_order_items';

    protected $guarded = [];

    protected $casts = [
        'inbound_info' => 'array'
    ];

    public function inboundOrder()
    {
        return $this->belongsTo(InboundOrder::class, 'inbound_id', 'id');
    }

    public function goods()
    {
        return $this->hasOne(Goods::class, 'id', 'goods_id');
    }

    public function goodsSku()
    {
        return $this->hasOne(GoodsSku::class, 'id', 'goods_sku_id');
    }

    public static function init($inboundId, $params)
    {
        return [
            'inbound_id' => $inboundId,
            'goods_id' => $params['goods_id'],
            'goods_sku_id' => $params['goods_sku_id'],
            'goods_name' => $params['goods_name'],
            'goods_sku' => $params['goods_sku'],
            'spec_name' => $params['spec_name'],
            'sku_image' => $params['sku_image'],
            'quantity' => $params['quantity'],
            'remark' => $params['remark'] ?? '',
            'created_at' => now(),
            'updated_at' => now(),
            'goods_type' => $params['goods_type'] ?? 1, //商品类型 1-产品 2-包材
            'packing_materials_type' => $params['packing_materials_type'] ?? 0, //包材类型 1-包装袋 2-纸箱 3-定制盒子 4-贴纸 5-卡片 99-其他'
        ];
    }

}
