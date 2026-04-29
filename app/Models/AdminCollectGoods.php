<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminCollectGoods extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_admin_collect_goods';

    protected $guarded = [];

    protected $casts = [
        'main_images' => 'array',
        'options' => 'array',
        'props' => 'array',
    ];

    CONST STATUS_DEFAULT = 0;
    CONST STATUS_CLAIM = 1;

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

    public function skus()
    {
        return $this->hasMany(AdminCollectGoodsSku::class, 'goods_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(GoodsCategory::class, 'category_id', 'id');
    }

    /**
     * 供应商
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/8/28 16:07
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    public static function statusList()
    {
        return [
            self::STATUS_DEFAULT => __('未认领'),
            self::STATUS_CLAIM => __('已认领'),
        ];
    }

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

    public function getStatusNameAttribute()
    {
        return self::statusList()[$this->status] ?? '-';
    }

    public function getGoodsTypeNameAttribute()
    {
        return self::goodsTypeList()[$this->goods_type] ?? '产品';
    }

    public function getPackingMaterialsTypeNameAttribute()
    {
        return self::packingMaterialsTypeList()[$this->packing_materials_type] ?? '-';
    }

    public static function init($params)
    {
        return [
            'spu' => $params['goods_id'] ?? $params['spu'],
            'goods_name' => $params['goods_name'],
            'category_id' => $params['category_id'] ?? 0,
            'category_name' => $params['category_name'] ?? '',
            'brand' => $params['brand'] ?? '',
            'unit' => $params['unit'] ?? '',
            'purchase_price' => $params['purchase_price'] ?? 0,
            'collect_url' => $params['collect_url'] ?? '',
            'collect_platform' => $params['collect_platform'] ?? '',
            'cover_image' => $params['cover_image'],
            'main_images' => $params['main_images'] ?? [],
            'options' => $params['options'] ?? [],
            'props' => $params['props'] ?? [],
            'detail' => $params['detail'] ?? '',
            'purchase_product_id' => $params['goods_id'] ?? '',
            'goods_type' => $params['goods_type'] ?? 1,
            'packing_materials_type' => $params['packing_materials_type'] ?? 0,
            'supplier_id' => $params['supplier_id'] ?? 0,
        ];
    }

}
