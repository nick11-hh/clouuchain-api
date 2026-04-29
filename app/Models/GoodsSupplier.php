<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsSupplier extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_goods_suppliers';

    protected $guarded = [];

    CONST TYPE_ALIBABA = 1;
    CONST TYPE_OFFLINE = 2;

    public static function typeList()
    {
        return [
            self::TYPE_ALIBABA => __('1688'),
            self::TYPE_OFFLINE => __('线下'),
        ];
    }

    public function goodsSku()
    {
        return $this->belongsTo(GoodsSku::class, 'goods_sku_id', 'id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    public function getPurchaseTypeNameAttribute()
    {
        return self::typeList()[$this->purchase_type] ?? '-';
    }


    public static function init($params, $opt = 1)
    {
        $data = [
            'supplier_id' =>  $params['supplier_id'],
            'price' =>  $params['price'],
            'currency' =>  $params['currency'] ?? 'CNY',
            'purchase_type' =>  $params['purchase_type'],
            'purchase_url' =>  $params['purchase_url'] ?? '',
            'purchase_goods_name' =>  $params['purchase_goods_name'] ?? '',
            'purchase_spec_image' =>  $params['purchase_spec_image'] ?? '',
            'purchase_spec_name' =>  $params['purchase_spec_name'] ?? '',
            'purchase_goods_id' =>  $params['purchase_goods_id'] ?? '',
            'purchase_sku_id' =>  $params['purchase_sku_id'] ?? '',
            'purchase_spec_id' =>  $params['purchase_spec_id'] ?? '',
            'is_default' =>  $params['is_default'] ?? 0,
        ];
        if ($opt == 1) {
            $data['goods_sku_id'] = $params['goods_sku_id'] ?? 0;
        }
        return $data;
    }

}
