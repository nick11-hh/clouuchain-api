<?php

namespace App\Models;

use App\Exceptions\AccidentException;
use App\Models\Traits\Basis;
use App\Models\Traits\CompanyLimitChecker;
use App\Models\Traits\CustomHasTranslations;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Class ExpressLineServicePrice
 * @package App\Models
 * @property int $value
 */
class ExpressLineServicePrice extends Model
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

    // 收取方式
    public const TAKEN_UN_FORCED = 0; // 自愿构选
    public const TAKEN_FORCED = 1;  //强制收取

    //用于翻译
    public $translatable = ['name'];

    protected $table = 'dsp_express_line_service_prices';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    /**
     * @return BelongsTo
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(ExpressLineRegion::class, 'region_id', 'id');
    }

    /**
     * @return BelongsTo
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(ExpressLineVAS::class, 'service_id', 'id');
    }
}
