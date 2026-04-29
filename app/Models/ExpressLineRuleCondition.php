<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class ExpressLineVAS
 * @package App\Models
 * @property int type
 * @property string $symbol
 * @property int $value
 */
class ExpressLineRuleCondition extends Model
{
    use Basis,
        HasValidateUnique,
        CustomHasTranslations;

    /**
     * 规则条件参数
     * 整单计费重、整单实重、整单体积重
     * 单箱计费重、单箱实重、单箱体积重
     * 单边最长、三边之和、箱数
     * 申报价值、运费价格
     */
    public const PARAM_PAYMENT_WEIGHT = 1;
    public const PARAM_ACTUAL_WEIGHT = 2;
    public const PARAM_VOLUME_WEIGHT = 3;
    public const PARAM_BOXES_PAYMENT_WEIGHT = 4;
    public const PARAM_BOXES_ACTUAL_WEIGHT = 5;
    public const PARAM_BOXES_VOLUME_WEIGHT = 6;
    public const PARAM_MAX_SIZE = 7;
    public const PARAM_SUM_SIZE = 8;
    public const PARAM_BOXES_COUNT = 9;
    public const PARAM_VALUE = 10;
    public const PARAM_FREIGHT_FEE = 11;
    public const PARAM_SECOND_MAX_SIZE = 12;
    public const PARAM_MAX_ADD_AVG_SECOND_ADD_MIN = 13; // =max + ((second + min) * 2)
    public const PARAM_MUL_SIZE = 14;
    public const PARAM_SUM_EACH_TWO_SIZE = 15;
    public const PARAM_ADDRESS_TAG = 16;
    public const PARAM_REMOTE_AREA = 17;
    public const PARAM_AVG_BOX_WEIGHT = 18;

    //用于翻译
    public $translatable = ['name'];

    protected $table = 'dsp_express_line_rule_conditions';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    /**
     * @return HasMany
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(ExpressLineServicePrice::class, 'service_id', 'id');
    }

    /**
     * @return BelongsToMany
     */
    public function userAddressTags(): BelongsToMany
    {
        return $this->belongsToMany(
            UserAddressTag::class,
            'dsp_user_address_tags_conditions',
            'condition_id',
            'tag_id'
        );
    }

    /**
     * @return BelongsToMany
     */
    public function remoteTypes()
    {
        return $this->belongsToMany(
            RemoteType::class,
            'dsp_remote_types_conditions',
            'condition_id',
            'remote_id'
        );
    }

    /**
     * @param int|null $status
     * @return array|string
     */
    public static function rules(int $status = null): array|string
    {
        $rules = [
            self::PARAM_PAYMENT_WEIGHT => __('整单计费重'),
            self::PARAM_ACTUAL_WEIGHT => __('整单实重'),
            self::PARAM_VOLUME_WEIGHT => __('整单体积重'),
            self::PARAM_BOXES_PAYMENT_WEIGHT => __('单箱计费重'),
            self::PARAM_BOXES_ACTUAL_WEIGHT => __('单箱实重'),
            self::PARAM_BOXES_VOLUME_WEIGHT => __('单箱体积重'),
            self::PARAM_MAX_SIZE => __('单边最长'),
            self::PARAM_SUM_SIZE => __('三边之和'),
            self::PARAM_BOXES_COUNT => __('箱数'),
            self::PARAM_VALUE => __('申报价值'),
            self::PARAM_SECOND_MAX_SIZE => __('次长边'),
            self::PARAM_MAX_ADD_AVG_SECOND_ADD_MIN => __('最长边+（次长边+短边） *2'),
            self::PARAM_MUL_SIZE => __('三边乘积'),
            self::PARAM_SUM_EACH_TWO_SIZE => __('任意两边之和'),
            self::PARAM_ADDRESS_TAG => __('地址标签'),
            self::PARAM_REMOTE_AREA => __('偏远地区'),
            self::PARAM_AVG_BOX_WEIGHT => __('平均箱重'),
        ];

        if (! $status) {
            return $rules;
        } else {
            return $rules[$status] ?? __('异常条件');
        }
    }

    /**
     * @return array|string
     */
    public function getParamNameAttribute(): array|string
    {
        return self::rules($this->param);
    }
}
