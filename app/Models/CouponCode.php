<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;

/**
 * 优惠券兑换码
 */
class CouponCode extends Model
{
    use Basis,
        CustomHasTranslations;

    public const STATUS_PROCESSING = 0;
    public const STATUS_INVALID = 9;

    public $translatable = ['remark'];

    protected $table = 'dsp_coupon_codes';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userCoupons()
    {
        return $this->hasMany(UserCoupon::class, 'code_id', 'id');
    }
}
