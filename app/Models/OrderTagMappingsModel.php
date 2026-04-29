<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 平台订单标签映射表
 */
class OrderTagMappingsModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_shop_order_tag_mappings';

    protected $guarded = [];
}
