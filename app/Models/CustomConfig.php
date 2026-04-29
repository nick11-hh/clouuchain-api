<?php

namespace App\Models;

use App\Models\Traits\Basis;

class CustomConfig extends Model
{
    use Basis;

    protected $table = 'dsp_custom_config';

    protected $guarded = [];
    public static function init($params)
    {
        return [
            'custom_id' => $params['custom_id'] ?? getCustomId(),
            'default_original_price_ratio' => $params['default_original_price_ratio'] ?? 1,
            'default_compare_original_price_ratio' => $params['default_compare_original_price_ratio'] ?? 1,
            'is_auto_payment' => $params['is_auto_payment'] ?? 0,
        ];
    }



}
