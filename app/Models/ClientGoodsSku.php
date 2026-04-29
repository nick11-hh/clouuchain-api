<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientGoodsSku extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_client_goods_skus';

    protected $guarded = [];

    protected $casts = [
        "spec_info" => 'array',
        "images" => 'array',
    ];

    public static function init($params, $shopifyReviewMode = 0)
    {
        return [
            'goods_id' => $params['goods_id'],
            'sku_id' => $params['sku_id'],
            'spec_name' => $params['spec_name'] ?? '',
            'spec_info' => $params['spec_info'] ?? [],
            'sale_price' => $params['sale_price'],
            'original_price' => $params['original_price'],
            'cost_price' => $params['sale_price'],
            'images' => $params['images'] ?? [],
            'quantity' => empty($params['quantity']) ? ($shopifyReviewMode ? 100 : 0) : $params['quantity'],
            'status' => $params['status'] ?? 1,
            'purchase_spec_id' => $params['purchase_spec_id'] ?? ''
        ];
    }

}
