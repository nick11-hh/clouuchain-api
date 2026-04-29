<?php

namespace App\Models;

use App\Services\Base\SystemConfigService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Goods extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_goods';

    protected $guarded = [];

    protected $casts = [
        'main_images' => 'array',
        'options' => 'array',
        'props' => 'array',
        'main_video' => 'array',
    ];

    public const STATUS_ENABLE = 1;
    public const STATUS_DISABLE = 0;

    //商品类型
    public const GOODS_TYPE_PRODUCT = 1;//产品
    public const GOODS_TYPE_PACKING_MATERIALS = 2;//包材
    public const GOODS_TYPE_VIRTUAL_PRODUCT = 3; //虚拟品

    public const GOODS_SOURCE_TYPE_ADD_MANUALLY  = 1; //后台添加
    public const GOODS_SOURCE_TYPE_IMPORT = 2; //导入

    //包材类型
    public const PACKING_MATERIALS_TYPE_BAG = 1;//包装袋
    public const PACKING_MATERIALS_TYPE_CARTON = 2;//纸箱
    public const PACKING_MATERIALS_TYPE_CUSTOM_BOX = 3;//定制盒子
    public const PACKING_MATERIALS_TYPE_TAGS = 4;//贴纸
    public const PACKING_MATERIALS_TYPE_CARD = 5;//卡片
    public const PACKING_MATERIALS_TYPE_OTHER = 99;//其他

    //审核状态
    public const GOOD_AUDIT_WAITING=0;
    public const GOOD_AUDIT_APPROVED=1;
    public const GOOD_AUDIT_REJECTED=2;

    //开发人员
    public function developer()
    {
        return $this->hasOne(Admin::class, 'id', 'developer_id');
    }

    public function skus()
    {
        return $this->hasMany(GoodsSku::class, 'goods_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(GoodsCategory::class, 'category_id', 'id');
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class, 'goods_id', 'id');
    }

    public static function statusList()
    {
        return [
            self::STATUS_DISABLE => __('已下架'),
            self::STATUS_ENABLE => __('已上架'),
        ];
    }

    public function goodsAudit()
    {
        return $this->hasOne(GoodsAudit::class, 'goods_id', 'id');
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
            self::GOODS_TYPE_VIRTUAL_PRODUCT => __('虚拟品'),
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

    public function getAuditStatusNameAttribute()
    {
        return self::auditStatusList()[$this->audit_status] ?? '-';
    }

    public function getGoodsTypeNameAttribute()
    {
        return self::goodsTypeList()[$this->goods_type] ?? '产品';
    }

    public function getPackingMaterialsTypeNameAttribute()
    {
        return self::packingMaterialsTypeList()[$this->packing_materials_type] ?? '-';
    }

    public function hasSelect()
    {
    return $this->hasOne(ClientGoods::class, 'spu', 'spu')->where('custom_id', getCustomId());
    }

    public static function init($params, $opt = 1)
    {
        $minSalePrices = $maxSalePrices = 0;
        if (!empty($params['skus'])) {
            $salePrices = array_column($params['skus'], 'sale_price');
            // 过滤掉空值，确保数组至少有一个元素
            $salePrices = array_filter($salePrices, function($price) {
                return $price !== null && $price !== '';
            });
            if (!empty($salePrices)) {
                $minSalePrices = min($salePrices);
                $maxSalePrices = max($salePrices);
            }
        }
        $data =  [
            'goods_name' => $params['goods_name'],
            'goods_name_cn' => $params['goods_name_cn'] ?? '',
            'category_id' => $params['category_id'] ?? 0,
            'brand' => $params['brand'] ?? '',
            'unit' => $params['unit'] ?? '',
            'purchase_price' => $params['purchase_price'] ?? 0,
            'min_sale_price' => $minSalePrices,
            'max_sale_price' => $maxSalePrices,
            'purchase_url' => $params['purchase_url'] ?? '',
            'alias' => $params['alias'] ?? '',
            'cover_image' => $params['cover_image'],
            'main_images' => $params['main_images'] ?? [],
            'main_video' => $params['main_video'] ?? [],
            'options' => $params['options'] ?? [],
            'props' => $params['props'] ?? [],
            'detail' => $params['detail'] ?? '',
            'purchase_platform' => $params['purchase_platform'] ?? '',
            'addr' => $params['addr'] ?? 0,
            'developer_id' => $params['developer_id'] ?? 0,
            'goods_type' => $params['goods_type'] ?? 1,
            'packing_materials_type' => $params['packing_materials_type'] ?? 0,
            'source_type' => $params['source_type'] ?? self::GOODS_SOURCE_TYPE_ADD_MANUALLY,
            'self_goods' => $params['self_goods'] ?? 0,
        ];

        if ($opt === 1) {
            $data['spu'] = $params['spu'] ?? self::getSpu();
            $data['purchase_product_id'] = $params['purchase_product_id'] ?? '';
            $data['is_group'] = $params['is_group'] ?? 0;
        }
        return $data;
    }

    public static function getSpu()
    {
        $pre = 'PR';
        if (Cache::has('adminProductSpu')) {
            $increment = Cache::increment('adminProductSpu');
        } else {
            $latest = self::query()->latest('id')->first();
            if (empty($latest)) {
                $increment = now()->unix();
            } else {
                // 只取 SPU 末尾 10 位数字并转为整数，避免包含字母导致 Redis::incrBy 报类型错误
                $increment = (int) substr($latest->spu, -10);
                $increment++;
            }
            // 首次写入使用 set，避免在 Redis::incrBy 中传入非法类型
            Cache::set('adminProductSpu', (int) $increment);
        }
        return $pre. $increment;
    }

    public function logistics()
    {
        return $this->hasOne(LogisticsCustomsDeclarationModel::class, 'product_id', 'id');
    }
}
