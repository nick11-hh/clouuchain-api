<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductQuoteApplyItem extends Model
{
    use HasFactory;

    protected $table = 'dsp_product_quote_apply_items';

    protected $guarded = [];

    const QUOTE_STATUS_NONE = 1; //未报价
    CONST QUOTE_STATUS_QUOTING = 2; //报价中
    CONST QUOTE_STATUS_QUOTED = 3; //已报价
    CONST QUOTE_STATUS_FAIL = 4; //拒绝报价


    public function goodsSku()
    {
        return $this->belongsTo(GoodsSku::class, 'goods_sku_id', 'id');
    }

    public static function statusList()
    {
        return [
            self::QUOTE_STATUS_NONE => __('未报价'),
            self::QUOTE_STATUS_QUOTING => __('报价中'),
            self::QUOTE_STATUS_QUOTED => __('已报价'),
            self::QUOTE_STATUS_FAIL => __('拒绝报价'),
        ];
    }

    public function getStatusNameAttribute()
    {
        return self::statusList()[$this->status] ?? '-';
    }


}
