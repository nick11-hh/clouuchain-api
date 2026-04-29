<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class LogisticsCustomsModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_logistics_customs';

    protected $casts = [
        'attributes' => 'array',
    ];
    public static function init($data)
    {
        return [
            'name'       => $data['name'],
            'cn_name'    => $data['cn_name'],
            'en_name'    => $data['en_name'],
            'unit_price' => $data['unit_price'],
            'code'       => $data['code'] ?? '',
            'weight'     => $data['weight'],
            'material'   => $data['material'] ?? '',
            'use_to'     => $data['use_to'] ?? '',
            'attributes' => $data['attributes'] ?? [],
        ];
    }
}
