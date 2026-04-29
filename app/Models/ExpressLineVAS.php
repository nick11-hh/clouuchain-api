<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 快递线路增值服务
 *
 * Class ExpressLineVAS
 * @package App\Models
 * @property int type
 * @property bool is_forced
 */
class ExpressLineVAS extends Model
{
    use Basis,
        HasValidateUnique,
        CustomHasTranslations;

    // 费用计算类型
    public const TYPE_PROPORTION = 1; //按运费比例
    public const TYPE_ORDER_FIXED = 2; //按订单固定收取
    public const TYPE_BOXES_FIXED = 3; //按订单箱数固定收取
    public const TYPE_PAYMENT_WEIGHT = 4; //按单位订单计费重量收取
    public const TYPE_ACTUAL_WEIGHT = 5; //按单位订单实际重量收取
    public const TYPE_VALUE = 6;        //按订单的申报价值比例收取
    public const TYPE_BOXES_SUB_ONE_FIXED = 7; //按订单箱数 - 1固定收取
    public const TYPE_PACKAGE_COUNT = 8; //按订单原始包裹数
    public const TYPE_PACKAGE_COUNT_SUB_THREE = 9; //按订单原始包裹数-3

    // 收取方式
    public const TAKEN_UN_FORCED = 0; // 自愿构选
    public const TAKEN_FORCED = 1;  //强制收取

    //用于翻译
    public $translatable = ['name', 'remark'];

    protected $table = 'dsp_express_line_services';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    /**
     * @return HasMany
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ExpressLineServicePrice::class, 'service_id', 'id');
    }

    /**
     * @param int $i
     * @return string
     */
    public static function getTypeName(int $i): string
    {
        $types =  [
            self::TYPE_PROPORTION => __('运费比例'),
            self::TYPE_ORDER_FIXED => __('整票固定费用'),
            self::TYPE_BOXES_FIXED => __('单箱固定费用'),
            self::TYPE_PAYMENT_WEIGHT => __('单位计费重量固定费用'),
            self::TYPE_ACTUAL_WEIGHT => __('单位实际重量固定费用'),
            self::TYPE_VALUE => __('申报价值比例'),
            self::TYPE_BOXES_SUB_ONE_FIXED => __('单箱固定费用（总箱数 - 1）'),
            self::TYPE_PACKAGE_COUNT => __('包裹固定费用（原始包裹数）'),
            self::TYPE_PACKAGE_COUNT_SUB_THREE => __('包裹固定费用（原始包裹数 - 3）'),
        ];

        return $types[$i] ?? '';
    }
}
