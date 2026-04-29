<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stock extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_stocks';

    protected $guarded = [];

    //商品类型
    public const GOODS_TYPE_PRODUCT = 1;//产品
    public const GOODS_TYPE_PACKING_MATERIALS = 2;//包材

    //包材类型
    public const PACKING_MATERIALS_TYPE_BAG = 1;//包装袋
    public const PACKING_MATERIALS_TYPE_CARTON = 2;//纸箱
    public const PACKING_MATERIALS_TYPE_CUSTOM_BOX = 3;//定制盒子
    public const PACKING_MATERIALS_TYPE_TAGS = 4;//贴纸
    public const PACKING_MATERIALS_TYPE_CARD = 5;//卡片
    public const PACKING_MATERIALS_TYPE_OTHER = 99;//其他

    /**
     * @desc 商品类型
     * @return array
     */
    public static function goodsTypeList(): array
    {
        return [
            self::PACKING_MATERIALS_TYPE_BAG => __('产品'),
            self::GOODS_TYPE_PACKING_MATERIALS => __('包材'),
        ];
    }

    /**
     * @desc 包材类型
     * @return array
     */
    public static function packingMaterialsTypeList(): array
    {
        return [
            self::GOODS_TYPE_PRODUCT => __('包装袋'),
            self::PACKING_MATERIALS_TYPE_CARTON => __('纸箱'),
            self::PACKING_MATERIALS_TYPE_CUSTOM_BOX => __('定制盒子'),
            self::PACKING_MATERIALS_TYPE_TAGS => __('贴纸'),
            self::PACKING_MATERIALS_TYPE_CARD => __('卡片'),
            self::PACKING_MATERIALS_TYPE_OTHER => __('其他'),
        ];
    }

    public function getGoodsTypeNameAttribute()
    {
        return self::goodsTypeList()[$this->goods_type] ?? '产品';
    }

    public function getPackingMaterialsTypeNameAttribute()
    {
        return self::packingMaterialsTypeList()[$this->packing_materials_type] ?? '-';
    }

    public function items()
    {
        return $this->hasMany(StockItem::class, 'stock_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(WarehouseAddress::class, 'warehouse_id', 'id');
    }

    public function customer()
    {
        return $this->hasOne(Custom::class, 'id', 'custom_id');
    }

}
