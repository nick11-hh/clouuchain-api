<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class AgentCommissionTemplate
 * @package App\Models
 * @property int mode
 * @property int type
 * @property int value
 */
class AgentCommissionTemplate extends Model
{
    use Basis,
        HasValidateUnique;

    public const TYPE_PROPORTION = 1;   // 按比例
    public const TYPE_AMOUNT = 2;   //按固定金额
    public const TYPE_UNIT_PAYMENT_WEIGHT = 3;   //按订单重单位金额
    public const TYPE_UNIT_ACTUAL_WEIGHT = 4;   //按订单实重单位重量
    public const TYPE_UNIT_VOLUME = 5;   //按订单单位体积

    protected $table = 'dsp_agent_commission_templates';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    /**
     * 模板的线路佣金
     *
     * @return BelongsToMany
     */
    public function templateCommissions(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpressLineModel::class,
            'dsp_agent_commission_template_lines',
            'template_id',
            'express_line_id'
        )->withPivot(['type', 'value']);
    }

    /**
     * 使用模板的代理
     *
     * @return HasMany
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'template_id', 'id');
    }

    /**
     * 佣金配置
     *
     * @return HasMany
     */
    public function configs(): HasMany
    {
        return $this->hasMany(AgentCommissionTemplateConfig::class, 'template_id', 'id');
    }
}
