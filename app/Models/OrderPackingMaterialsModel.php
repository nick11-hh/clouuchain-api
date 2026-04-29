<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class OrderPackingMaterialsModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_shop_order_packing_materials';

    protected $guarded = [];

    protected $casts = [
        "images" => 'array',
    ];

    public function admin()
    {
        return $this->hasOne(Admin::class, 'id', 'operator_id');
    }

    public static function init($params): array
    {
        return [
            'order_id'     => $params['order_id'] ?? 0, //订单ID',
            'goods_id'     => $params['goods_id'] ?? 0, //商品ID',
            'goods_sku_id' => $params['goods_sku_id'] ?? 0, //sku主键',
            'name'         => $params['name'] ?? '', //商品名称',
            'sku'          => $params['sku'] ?? '', //SKU',
            'spec_name'    => $params['spec_name'] ?? '', //规格名称',
            'quantity'     => $params['quantity'] ?? 0, //数量',
            'images'       => $params['images'] ?? [], //商品图片',
            'operator_id'  => $params['operator_id'] ?? auth()->id(), //操作人ID',
        ];
    }

}
