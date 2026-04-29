<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsDiscountRuleItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_goods_discount_rule_items';

    protected $guarded = [];

    public function rule()
    {
        return $this->belongsTo(GoodsDiscountRule::class, 'rule_id', 'id');
    }

    public function goods()
    {
        return $this->belongsTo(Goods::class, 'goods_id', 'id');
    }
}
