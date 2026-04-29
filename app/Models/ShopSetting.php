<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopSetting extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_shop_setting';

    protected $guarded = [];

    const DELIVERY_HEAD_AND_LAST = 1;
    const DELIVERY_HEAD_NOT_EMAIL_AND_LAST = 2;
    const DELIVERY_HEAD_NOT_LAST = 3;
    const DELIVERY_NOT_HEAD_AND_LAST = 4;

    public function shop()
    {
        return $this->belongsTo(ShopModel::class, 'shop_id', 'id');
    }
}
