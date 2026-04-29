<?php

namespace App\Models;

use App\Lib\Platform;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShoppingCart extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_shopping_cart';

    protected $guarded = [];

    protected $casts = [
        'skus' => 'array',
    ];

    /**
     * 关联产品开发
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/10/31 18:43
     */
    public function goods()
    {
        return $this->belongsTo(Goods::class, 'spu', 'spu');
    }

    public static function init($params)
    {
        return [
            'user_id' => $params['user_id'] ?? getUserId(),
            'custom_id' => $params['custom_id'] ?? getCustomId(),
            'platform' => $params['platform'] ?? Platform::LOCAL,
            'goods_id' => $params['goods_id'] ?? 0,
            'goods_name' => $params['goods_name'] ?? '',
            'spu' => $params['spu'] ?? '',
            'sku_gross_weight' => $params['sku_gross_weight'] ?? 0,
            'source_url' => $params['source_url'] ?? '',
            'cover_image' => $params['cover_image'] ?? '',
            'skus' => $params['skus'] ?? [],
            'goods_type' => $params['goods_type'] ?? 1, //商品类型 1-产品 2-包材
            'self_goods' => $params['self_goods'] ?? 0
        ];
    }

    public static function getCartsNum()
    {
        return self::query()->where(['user_id' => getUserId()])->count();
    }

}
