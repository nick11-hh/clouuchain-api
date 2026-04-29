<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class ExpressLineVAS
 * @package App\Models
 * @property int type 类型
 * @property bool is_and 是否与条件
 * @property int charge_mode 计费模式
 * @property int value 计费值
 * @property int min_charge 最小计费值
 * @property int max_charge 最大计费值
 * @property string notice 限制出库提示
 */
class ExpressLineRule extends Model
{
    use Basis,
        CustomHasTranslations;

    // 规则处理类型
    // 1 按订单收费 2 按箱子收费 3 按单位重量收费 4 限制出仓 5 高级模式
    public const TYPE_ORDER = 1;
    public const TYPE_ORDER_BOXES = 2;
    public const TYPE_ORDER_WEIGHT = 3;
    public const TYPE_FORBIDDEN = 4;
    public const TYPE_ADVANCED = 5;

    // 费用计算类型
    public const CHARGE_MODE_FIXED = 1;     // 固定金额
    public const CHARGE_MODE_VALUE_PROPORTION = 2; //按订单价值比例
    public const CHARGE_MODE_FREIGHT_PROPORTION = 3; //按运费比例
    public const CHARGE_MODE_ADVANCED = 4; // 高级模式，按公式计算

    //用于翻译
    public $translatable = ['name', 'notice'];

    protected $table = 'dsp_express_line_rules';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    /**
     * 规则条件
     *
     * @return HasMany
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(ExpressLineRuleCondition::class, 'rule_id', 'id');
    }

    /**
     * 规则关联区域
     *
     * @return BelongsToMany
     */
    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpressLineRegion::class,
            'dsp_express_line_rules_regions',
            'rule_id',
            'region_id'
        );
    }
}
