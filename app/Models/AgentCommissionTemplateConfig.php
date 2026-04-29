<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;

/**
 * Class AgentCommissionTemplateConfig
 * @package App\Models
 * @property int mode
 * @property int type
 * @property int value
 * @property int level
 */
class AgentCommissionTemplateConfig extends Model
{
    use Basis;

    public const TYPE_PROPORTION = 1;   // 按比例
    public const TYPE_AMOUNT = 2;   //按固定金额
    public const TYPE_UNIT_AMOUNT = 3;   //按订单重单位金额

    protected $table = 'dsp_agent_commission_template_configs';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    /**
     * 模板的线路佣金
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function lineCommissions()
    {
        return $this->belongsToMany(
            ExpressLineModel::class,
            'dsp_agent_commission_template_config_lines',
            'config_id',
            'express_line_id'
        )->withPivot(['type', 'value']);
    }
}
