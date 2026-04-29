<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CompanyLimitChecker;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Agent
 * @package App\Models
 * @property int mode
 * @property int type
 * @property int commission
 */
class Agent extends Model
{
    use Basis,
        SoftDeletes,
        CompanyLimitChecker,
        HasValidateUnique;

    //线路抽成类型
    public const RATE = 1; // 按比例抽成
    public const FIXED = 2; // 按固定金额抽成
    public const UNIT_WEIGHT = 3; // 按单位计费重量
    public const UNIT_ACTUAL_WEIGHT = 4; // 按单位实际重量
    public const TYPE_UNIT_VOLUME = 5;   //按订单单位体积

    //计算佣金的金额
    public const MODE_PAYMENT = 0; // 默认：按支付金额 - 实付金额 减去了优惠金额
    public const MODE_FREIGHT = 1; // 按运费金额
    public const MODE_ORDER_FEE = 2; // 按订单费用 - 应付金额

    protected $table = 'dsp_agents';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    /**
     * 代理佣金记录
     */
    public function commissions()
    {
        return $this->hasMany(AgentCommission::class, 'agent_id', 'agent_id');
    }

    /**
     * 代理的线路佣金
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function lineCommissions()
    {
        return $this->belongsToMany(
            ExpressLineModel::class,
            'dsp_agent_commission_express_line',
            'agent_id',
            'express_line_id',
            'id'
        )->withPivot(['type', 'commission']);
    }

    /**
     * 代理用户
     */
    public function agentUsers()
    {
        return $this->hasMany(User::class, 'invite_id', 'agent_id');
    }

    /**
     * 代理用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'agent_id', 'id');
    }

    /**
     * 提现申请
     *
     * @return HasMany
     */
    public function withdraws(): HasMany
    {
        return $this->hasMany(AgentCommissionWithdrawRecord::class, 'user_id', 'agent_id');
    }

    /**
     * 佣金模板
     *
     * @return HasOne
     */
    public function template(): HasOne
    {
        return $this->hasOne(AgentCommissionTemplate::class, 'id', 'template_id');
    }

    /**
     * 多级佣金配置
     *
     * @return HasMany
     */
    public function configs()
    {
        return $this->hasMany(AgentConfig::class, 'agent_id', 'id');
    }

    /**
     * 佣金累计订单数
     * @return mixed
     */
    public function getTotalOrderAttribute()
    {
        $sum = 0;

        $this->agentUsers->flatMap(function ($value) use (&$sum) {
            $sum += $value->orders->count();
        });

        return $sum;
    }


    /**
     * 佣金订单金额
     * @return mixed
     */
    public function getCountOrderFeeAttribute()
    {
        $sum = 0;

        $this->agentUsers->flatMap(function ($value) use (&$sum) {
            $sum += $value->orders->sum->actual_payment_fee;
        });

        return $sum;
    }

    /**
     * 成交订单数
     * @return mixed
     */
    public function getDealOrderAttribute()
    {
        return $this->commissions->filter(function ($value) {
            return (int) $value->order->status >= Order::WAIT_WAREHOUSE_TRAN;
        })->count();
    }

    /**
     * 合计提成
     * @return int
     */
    public function getCountAmountAttribute()
    {
        $sum = 0;
        $this->withdraws->flatMap(function ($value) use (&$sum) {
            $sum += $value->amount;
        });

        return $sum;
    }

    /**
     * 已结算提成
     * @return int
     */
    public function getCheckAmountAttribute()
    {
        $sum = 0;
        $this->withdraws->flatMap(function ($value) use (&$sum) {
            if ($value->status == AgentCommissionWithdrawRecord::CHECK_SUCCESS)
                $sum += $value->amount;
        });

        return $sum;
    }

    /**
     * 未结算提成
     * @return int
     */
    public function getNotCheckAttribute()
    {
        $sum = 0;
        $this->withdraws->flatMap(function ($value) use (&$sum) {
            if ($value->status == AgentCommissionWithdrawRecord::WAIT_CHECK)
                $sum += $value->amount;
        });

        return $sum;
    }

    /**
     * 拒绝提成
     * @return int
     */
    public function getRefuseAmountAttribute()
    {
        $sum = 0;
        $this->withdraws->flatMap(function ($value) use (&$sum) {
            if ($value->status == AgentCommissionWithdrawRecord::CHECK_FAIL)
                $sum += $value->amount;
        });

        return $sum;
    }

    /**
     * 是否启用
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled === 1;
    }

    /**
     * @param  bool  $bool
     * @return bool
     */
    public function shouldNotify(bool $bool)
    {
        return self::update([
            'should_notify' => $bool,
        ]);
    }
}
