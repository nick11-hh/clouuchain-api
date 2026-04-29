<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class LogisticsCustomsDeclarationModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_logistics_customs_declaration';

    protected $casts = [
        'attributes' => 'array',
    ];

    public static function init($product_id, $data): array
    {
        return [
            'product_id'   => $product_id,
            'cn_name'      => $data['cn_name'] ?? '',
            'en_name'      => $data['en_name'] ?? '',
            'unit_price'   => $data['unit_price'] ?? 0,
            'code'         => $data['code'] ?? '',
            'weight'       => $data['weight'] ?? 0,
            'address'      => $data['address'] ?? '',
            'attributes'   => $data['attributes'] ?? '',
            'material'     => $data['material'] ?? '',
            'use_to'       => $data['use_to'] ?? '',
            'goods_sku_id' => $data['goods_sku_id'] ?? 0,
        ];
    }

}
