<?php

namespace App\Models;

use App\Models\Traits\Basis;

/**
 * 优惠券类型
 */
class CouponType extends Model
{
    use Basis;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $table = 'dsp_coupon_types';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];
}
