<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CompanyLimitChecker;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class AgentConfig
 * @package App\Models
 * @property int level
 * @property int mode
 * @property int type
 * @property int value
 */
class AgentConfig extends Model
{
    use Basis;

    //线路抽成类型
    public const RATE = 1; // 按比例抽成
    public const FIXED = 2; // 按固定金额抽成
    public const UNIT_WEIGHT = 3; // 按单位计费重量

    //计算佣金的金额
    public const MODE_PAYMENT = 0; // 默认：按支付金额
    public const MODE_FREIGHT = 1; // 按运费金额
    public const MODE_ORDER_FEE = 2; // 按订单费用

    protected $table = 'dsp_agents';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    /**
     * 代理用户
     */
    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'id');
    }
}
