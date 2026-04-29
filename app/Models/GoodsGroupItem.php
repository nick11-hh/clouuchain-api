<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsGroupItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_goods_group_items';

    protected $guarded = [];

    public function goodsSku()
    {
        return $this->hasOne(GoodsSku::class, 'id', 'goods_sku_id');
    }
}
