<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformProduct extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_platform_products';

    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'images' => 'array',
        'apply_country_ids' => 'array'
    ];

    const QUOTE_STATUS_NONE = 1; //未报价
    CONST QUOTE_STATUS_QUOTING = 2; //报价中
    CONST QUOTE_STATUS_QUOTED = 3; //已报价
    CONST QUOTE_STATUS_FAIL = 4; //拒绝报价
    const QUOTE_WAIT_CONFIRM = 5; //待确认报价
    const QUOTE_PART_CONFIRM = 6; //部分接受报价

    const REJECT_REASON_NO_AVAILABLE_ITEM = 1; //暂无现货
    const REJECT_REASON_PRICE_IS_TOO_HIGH = 2; //价格太高
    const REJECT_REASON_NO_STOCK = 3; //没有库存
    const REJECT_REASON_OTHER = 4; //其他

    public static function quoteStatusList()
    {
        return [
            self::QUOTE_STATUS_NONE => __('未报价'),
            self::QUOTE_STATUS_QUOTING => __('报价中'),
            self::QUOTE_STATUS_QUOTED => __('已报价'),
            self::QUOTE_STATUS_FAIL => __('拒绝报价'),
            self::QUOTE_WAIT_CONFIRM => __('待确认'),
            self::QUOTE_PART_CONFIRM => __('部分接受'),
        ];
    }

    public function skus()
    {
        return $this->hasMany(PlatformProductSku::class, 'product_id', 'id');
    }

    public function shop()
    {
        return $this->belongsTo(ShopModel::class, 'shop_id', 'id');
    }

    public function logisticsChannel()
    {
        return $this->hasOne(ExpressLineModel::class, 'id', 'logistics_channel_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    /**
     * 关联客户模型
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/10/18 15:30
     */
    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id');
    }

    public function getQuoteStatusNameAttribute()
    {
        return self::quoteStatusList()[$this->quote_status] ?? '-';
    }

    /**
     * 获取拒绝原因列表
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/10/28 19:06
     */
    public static function getRejectReasonList()
    {
        return [
            self::REJECT_REASON_NO_AVAILABLE_ITEM => __('暂无现货'),
            self::REJECT_REASON_PRICE_IS_TOO_HIGH => __('价格太高'),
            self::REJECT_REASON_NO_STOCK => __('无库存'),
            self::REJECT_REASON_OTHER => __('其他'),
        ];
    }

    /**
     * 获取拒绝原因名称属性
     * @return mixed|string
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/10/28 19:06
     */
    public function getRejectReasonNameAttribute()
    {
        return self::getRejectReasonList()[$this->reject_reason] ?? '-';
    }

    public static function init($params, $opt = 1)
    {
        $data = [
            'product_name' => $params['product_name'],
            'product_type' => $params['product_type'] ?? '',
            'tags' => '',
            'status' => $params['status'] ?? '',
            'options' => $params['options'] ?? null,
            'images' => $params['images'] ?? null,
            'detail' => $params['detail'] ?? '',
            'published_at' => $params['published_at'] ?? null,
        ];
        if ($opt == 1) {
            $data['product_id'] = $params['product_id'];
            $data['custom_id'] = $params['custom_id'];
            $data['shop_id'] = $params['shop_id'];
            $data['shop_type'] = $params['shop_type'];
        }
        return $data;
    }
}
