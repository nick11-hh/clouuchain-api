<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class HandMovementModel extends Model
{
    use HasFactory;
    protected $table = 'dsp_order_logistics_customs_hand_movement';

    protected $casts = [
        'attributes' => 'array',
    ];

    public static function init($order_id, $data)
    {
        return [
            'order_id' => $order_id,
            'cn_name' => $data['cn_name'],
            'en_name' => $data['en_name'],
            'unit_price' => $data['unit_price'],
            'weight' => $data['weight'],
            'material' => $data['material'] ?? '',
            'use_to' => $data['use_to'] ?? '',
            'code' => $data['code'] ?? '',
            'attributes' => $data['attributes'] ?? [],
        ];
    }
}
