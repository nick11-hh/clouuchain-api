<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class AdminCollectGoodsSku extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_admin_collect_goods_skus';

    protected $guarded = [];

    protected $casts = [
        'spec_info' => 'array',
        'images' => 'array'
    ];

    /**
     * 利润率系数 (20% 利润率)默认值
     */
    private const PROFIT_MARGIN_FACTOR = 0.8;

    public function goods()
    {
        return $this->belongsTo(AdminCollectGoods::class, 'goods_id', 'id');
    }

    /**
     * 初始化商品 SKU 数据
     */
    public static function init($params)
    {
        $salePrice = self::calculateSalePrice($params['origin_price'] ?? 0);

        return [
            'goods_id' => $params['goods_id'] ?? null,
            'sku_id' => $params['sku_id'] ?? null,
            'prop_id' => $params['prop_id'] ?? null,
            'spec_name' => $params['spec_name'] ?? '',
            'spec_info' => $params['spec_info'] ?? [],
            'sale_price' => $salePrice,
            'compare_price' => $params['compare_price'] ?? $params['sale_price'] ?? 0,
            'images' => $params['images'] ?? [],
            'status' => $params['status'] ?? 1,
            'purchase_spec_id' => $params['spec_id'] ?? '',
            'length' => $params['length'] ?? 0,
            'width' => $params['width'] ?? 0,
            'height' => $params['height'] ?? 0,
            'weight' => $params['weight'] ?? 0,
            'purchase_price' => $params['origin_price'] ?? $params['sale_price'] ?? 0,
            'quote_price' => 0,
        ];
    }

    /**
     * 计算销售价格
     * 公式：原价 -> 加上利润率
     */
    private static function calculateSalePrice($originPrice)
    {
        if ($originPrice <= 0) {
            return 0;
        }

        // 将人民币销售价格除以利润率系数得到最终售价
        $finalPrice = bcdiv($originPrice, self::PROFIT_MARGIN_FACTOR, 4);

        return round($finalPrice, 2);
    }
}
