<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomsQuoteConfig extends Model
{
    const FREIGHT_QUOTE_AMOUNT_TYPE_1 = 1;
    const FREIGHT_QUOTE_AMOUNT_TYPE_2 = 2;

    const PRODUCT_QUOTE_CALCULATE_METHOD_1 = 1;
    const PRODUCT_QUOTE_CALCULATE_METHOD_2 = 2;

    const FREIGHT_QUOTE_CALCULATE_METHOD_1 = 1;
    const FREIGHT_QUOTE_CALCULATE_METHOD_2 = 2;

    protected $table = 'dsp_customs_quote_config';

    protected $guarded = [];

    public static array $freightQuoteAmountType = [
        self::FREIGHT_QUOTE_AMOUNT_TYPE_1  => '实际报价',
        self::FREIGHT_QUOTE_AMOUNT_TYPE_2  => '物流成本',
    ];

    public static array $productQuoteCalculateMethod = [
        self::PRODUCT_QUOTE_CALCULATE_METHOD_1  => '按百分比计算',
        self::PRODUCT_QUOTE_CALCULATE_METHOD_2  => '按固定金额计算',
    ];
    
    public static array $freightQuoteCalculateMethod = [
        self::FREIGHT_QUOTE_CALCULATE_METHOD_1  => '按百分比计算',
        self::FREIGHT_QUOTE_CALCULATE_METHOD_2  => '按固定金额计算',
    ];
}
