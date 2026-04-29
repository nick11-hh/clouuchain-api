<?php

namespace App\Models;

use App\Helper\CurrencyConverter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class GoodsSku extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_goods_skus';

    protected $guarded = [];

    protected $casts = [
        "spec_info" => 'array',
        "images" => 'array',
    ];

    public function goods()
    {
        return $this->belongsTo(Goods::class, 'goods_id', 'id');
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class, 'sku_id', 'id');
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseOrdersItemsModel::class, 'sku_id', 'id');
    }

    public function goodsSuppliers()
    {
        return $this->hasMany(GoodsSupplier::class, 'goods_sku_id', 'id');
    }

    public function logistics()
    {
        return $this->hasOne(LogisticsCustomsDeclarationModel::class, 'goods_sku_id', 'id');
    }

    public function orderItemMapping()
    {
        return $this->hasMany(OrderItemMapping::class, 'goods_sku_id', 'id');
    }

    public function skuQuotationGroup()
    {
        return $this->hasMany(SkuQuotationGroupModel::class, 'sku_id', 'id');
    }

    //采购员
    public function purchaser()
    {
        return $this->hasOne(Admin::class, 'id', 'purchase_buyer_id');
    }

    public function groupItems()
    {
        return $this->hasMany(GoodsGroupItem::class, 'group_sku_id', 'id');
    }

    public function groupSkus()
    {
        return $this->hasManyThrough(GoodsSku::class,
            GoodsGroupItem::class,
            'group_sku_id',
            'id',
            'id',
            'goods_sku_id'
        );
    }

    public static function init($params, $opt = 1, $goodsData = [])
    {
//        $params['profit'] = $params['profit'] ?? 0;
        $params['purchase_price'] = $params['purchase_price'] ?? 0;

        $data = [
            'spec_name' => $params['spec_name'] ?? '',
            'spec_name_cn' => $params['spec_name_cn'] ?? '',
            'spec_info' => $params['spec_info'] ?? [],
            'sale_price' => $params['sale_price'] ?? 0,
            'purchase_price' => $params['purchase_price'],
            'images' => !empty($params['images']) ? $params['images'] : [$goodsData['cover_image'] ?? ''],
            'quantity' => $params['quantity'] ?? 0,
            'status' => $params['status'] ?? 1,
            'sku_name' => $params['sku_name'] ?? '',
            'length' => $params['length'] ?? 0,
            'width' => $params['width'] ?? 0,
            'height' => $params['height'] ?? 0,
            'weight' => $params['weight'] ?? 0,
            // 'original_price' => $params['original_price'] ?? 0, //暂时去掉原价
            'original_price' => 0,
            'sku_remark' => $params['sku_remark'] ?? '',
            'purchase_days' => $params['purchase_days'] ?? 0,
            'min_purchase_quantity' => $params['min_purchase_quantity'] ?? 0,
            'purchase_buyer_id' => $params['purchase_buyer_id'] ?? 0,
            'purchase_remark' => $params['purchase_remark'] ?? '',
            'sku_id' => $params['sku_id'] ?? 0,
            'prop_id' => $params['prop_id'] ?? 1,
        ];

        $currencyConverter = new CurrencyConverter();
        //报价价格计算设置方式 1 利润=产品报价 - 采购价 2 报价=采购价 + 利润
        $quotePriceSettingType = $params['quote_price_setting_type'] ?? 1;
        $data['quote_price'] = $params['quote_price'] ?? 0;
//        $data['sale_price'] = $params['quote_price'] ?? 0;
        $data['profit'] = 0;//报价先直接保存前端计算好的
        $data['profit_margin'] = $params['profit_margin'] ?? 20;
//        $data['profit'] = $params['profit'] ?? 0;//报价先直接保存前端计算好的
        $data['is_group'] = $params['is_group'] ?? 0;

        if ($opt === 1) {
            $data['goods_id'] = $params['goods_id'];
            $data['purchase_spec_id'] = $params['purchase_spec_id'] ?? '';
            $data['system_sku'] = $params['system_sku'] ?? self::getSystemSku();
        } else {
            if (isset($params['system_sku']) && empty($params['system_sku'])) {
                $data['system_sku'] = self::getSystemSku();
            }
        }
        return $data;
    }

    /**
     * 生成系统sku ps：T0000001
     */
    public static function getSystemSku()
    {
        $pre = 'T';
        $cacheKey = 'adminSystemSku' . getCurrentUuid();
        if (Cache::has($cacheKey)) {
            $increment = Cache::increment($cacheKey);
        } else {
            $systemSku = self::query()->latest('id')->value('system_sku');
            if (empty($systemSku)) {
                $increment = 1;
            } else {
                // 只取末尾 7 位数字并转为整数，避免非数字内容导致 Redis::incrBy 类型错误
                $increment = (int) substr($systemSku, -7);
                $increment++;
            }
            // 首次写入使用 set，避免在 Redis::incrBy 中传入非法类型
            Cache::set($cacheKey, (int) $increment);
        }
        return $pre. str_pad($increment, 7, 0, STR_PAD_LEFT);
    }

}
