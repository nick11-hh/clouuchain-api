<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class SkuQuotationGroupModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_sku_quotation_group';

    protected $casts = [
        'express_line_info' => 'array'
    ];


    public static function init($skuId, $customId, $countryId)
    {
        return [
            'sku_id' => $skuId,
            'custom_id' => $customId,
            'country_id' => $countryId,
        ];
    }

    public function goodsSku()
    {
        return $this->belongsTo(GoodsSku::class, 'sku_id', 'id');
    }
    public function goods()
    {
        return $this->belongsTo(Goods::class, 'goods_id', 'id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id');
    }

    public function skuQuotationGroupAttr()
    {
        return $this->hasMany(SkuQuotationGroupAttrModel::class, 'parent_id', 'id')->where('is_new', 1);
    }

    public function skuQuotationGroupAttrHistory()
    {
        return $this->hasMany(SkuQuotationGroupAttrModel::class, 'parent_id', 'id');
    }

    public function expressLine()
    {
        return $this->belongsTo(ExpressLineModel::class, 'express_line_id', 'id');
    }
}
