<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * 客户已选择商品模型
 * Class ClientGoods
 * @package App\Models
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/11/29 11:12
 */
class ClientGoods extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_client_goods';

    protected $guarded = [];

    protected $casts = [
        'main_images' => 'array',
        'options' => 'array',
        'props' => 'array',
    ];

    const STATUS_DEFAULT = 0;
    const STATUS_PUBLISHED = 1;
    const STATUS_PUBLISHING = 2;

    public function skus()
    {
        return $this->hasMany(ClientGoodsSku::class, 'goods_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(GoodsCategory::class, 'category_id', 'id');
    }

    public function publishLog()
    {
        return $this->hasOne(ClientGoodsPublishLog::class, 'goods_id', 'id')->latest('id');
    }

    public function publishLogs()
    {
        return $this->hasMany(ClientGoodsPublishLog::class, 'goods_id', 'id');
    }

    public static function statusList()
    {
        return [
            self::STATUS_DEFAULT => __('未推送'),
            self::STATUS_PUBLISHED => __('已推送'),
            self::STATUS_PUBLISHING => __('推送中'),
        ];
    }

    public function getStatusNameAttribute()
    {
        return self::statusList()[$this->status];
    }

    public static function init($params)
    {
        return [
            'custom_id' => $params['custom_id'] ?? getCustomId(),
            'spu' => $params['spu'] ?? self::getSpu(),
            'goods_name' => $params['goods_name'],
            'category_id' => $params['category_id'] ?? 0,
            'category_name' => $params['category_name'] ?? '',
            'brand' => $params['brand'] ?? '',
            'unit' => $params['unit'] ?? '',
            'source_url' => $params['source_url'] ?? '',
            'cover_image' => $params['cover_image'],
            'main_images' => $params['main_images'] ?? [],
            'options' => $params['options'] ?? [],
            'props' => $params['props'] ?? [],
            'detail' => $params['detail'] ?? '',
            'purchase_platform' => $params['purchase_platform'] ?? '',
            'purchase_product_id' => $params['purchase_product_id'] ?? '',
            'goods_type' => $params['goods_type'] ?? 1, //商品类型 1-产品 2-包材
        ];
    }

    public static function getSpu()
    {
        $pre = 'PD';
        if (Cache::has('clientProductSpu')) {
            $increment = Cache::increment('clientProductSpu');
        } else {
            $latest = self::query()->latest('id')->first();
            if (empty($latest)) {
                $increment = now()->unix();
            } else {
                $increment = substr($latest->spu, -10);
            }
            Cache::increment('clientProductSpu', $increment);
        }
        return $pre. $increment;
    }
}
