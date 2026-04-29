<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformProductSku extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_platform_product_skus';

    protected $guarded = [];

    protected $casts = [
        'images' => 'array'
    ];

    public function mapping()
    {
        return $this->hasOne(OrderItemMapping::class, 'platform_variant_id', 'platform_sku_id');
    }

    public function goodsSku()
    {
        return $this->hasOneThrough(GoodsSku::class, OrderItemMapping::class, 'platform_variant_id', 'id', 'platform_sku_id', 'goods_sku_id');
    }

    public function applyMapping()
    {
        return $this->hasOne(ProductQuoteApplyItem::class, 'platform_variant_id', 'platform_sku_id');
    }

    public function platformProduct()
    {
        return $this->belongsTo(PlatformProduct::class, 'product_id', 'id');
    }

    public static function init($params, $opt = 1)
    {
        $data = [
            'sku' => $params['sku'] ?: '',
            'barcode' => $params['barcode'] ?? null,
            'title' => $params['title'] ?? '',
            'option' => $params['option'] ?? '',
            'price' => isset($params['price']) && empty($params['price']) ? 0 : $params['price'],
            'inventory_quantity' => $params['inventory_quantity'] ?? 0,
            'compare_at_price' => $params['compare_at_price'] ?? 0,
            'inventory_item_id' => $params['inventory_item_id'] ?? 0,
            'images' => $params['images'] ?? []
        ];
        if ($opt == 1) {
            $data['product_id'] = $params['product_id'];
            $data['platform_product_id'] = $params['platform_product_id'];
            $data['platform_sku_id'] = $params['platform_sku_id'];
        }
        return $data;
    }
}
