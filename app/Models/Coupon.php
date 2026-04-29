<?php

namespace App\Models;

use App\Casts\ImageUrl;
use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use Illuminate\Database\Eloquent\Builder;

/**
 * 优惠券
 */
class Coupon extends Model
{
    use Basis, CustomHasTranslations;

    public const COUPON_NOT_EFFECTED = 1;
    public const COUPON_EFFECTED = 2;
    public const COUPON_EXPIRED = 3;
    public const COUPON_USE_DAYS = 4;

    //优惠券类型
    public const VOUCHER = 1; // 抵用券 -- 默认的
    public const TYPE_NEW_CUSTOM = 2; // 新用户福利券
    public const TYPE_SHARED = 3; // 分享领的券
    public const TYPE_PROPORTION = 4; // 比例折扣
    public const TYPE_SUB_WEIGHT = 5; // 减重券

    //Scope 优惠券范围
    public const SCOPE_NOT_LIMITED = 0; // 不限路线
    public const SCOPE_SPECIFY_LINES = 1; // 限指定路线
    public const SCOPE_SPECIFY_COUNTRIES = 2; // 限指定国家


    // 折扣类型
    public const DISCOUNT_TYPE_MONEY = 0; // 折扣券
    public const DISCOUNT_TYPE_FIRST = 1; // 首重券
    public const DISCOUNT_TYPE_WEIGHT = 2; // 重量券
    public const DISCOUNT_TYPE_PROPORTION = 3; // 比例折扣券

    public const DISCOUNT_METHOD_ALL = 0; // 所有费用
    public const DISCOUNT_METHOD_FREIGHT = 1; // 运费

    public $translatable = ['name'];

    public static $status = [
        self::COUPON_NOT_EFFECTED => '未生效',
        self::COUPON_EFFECTED => '进行中',
        self::COUPON_EXPIRED => '已失效',
    ];

    protected $table = 'jiyun_coupons';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'enabled' => 'bool',
        'share_qr_code' => ImageUrl::class,
    ];

    /**
     * 类型
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function type()
    {
        return $this->belongsTo(CouponType::class, 'coupon_type_id', 'id');
    }

    /**
     * 用户优惠券
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userCoupons()
    {
        return $this->hasMany(UserCoupon::class, 'coupon_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function codes()
    {
        return $this->hasMany(CouponCode::class, 'coupon_id', 'id');
    }

    /**
     * 添加优惠券
     *
     * @param $data
     * @return mixed
     */
    public static function addCoupon($data)
    {
        return self::create([
            'name' => $data['name'],
            'coupon_type_id' => self::TYPE_NEW_CUSTOM,
            'amount' => $data['amount'],
            'threshold' => $data['threshold'],
            'enabled' => true,
            'total_count' => 0,
            'used_count' => 0,
            'effected_at' => $data['effected_at'],
            'expired_at' => $data['expired_at'],
            'company_id' => $data['company_id'],
        ]);
    }

    /**
     * 待生效
     * @param  Builder $builder
     * @return Builder
     */
    public function scopeNotEffected(Builder $builder)
    {
        return $builder->where(
            [
                ['effected_at', '>', now()],
                ['enabled', '=', true],
            ]
        );
    }

    /**
     * 生效
     * @param  Builder  $builder
     * @return Builder
     */
    public function scopeEffected(Builder $builder)
    {
        return $builder->where(
            [
                ['effected_at', '<=', now()],
                ['expired_at', '>=', now()],
                ['enabled', '=', true],
            ]
        );
    }

    /**
     * 失效
     * @param  Builder  $builder
     * @return Builder
     */
    public function scopeExpired(Builder $builder)
    {
        return $builder->where(
            [
                ['expired_at', '<', now()],
                ['enabled', '=', true],
            ]
        )->orWhere('enabled', false);
    }

    /**
     * 获取优惠券状态
     * @return int
     */
    public function getCouponStatusAttribute(): int
    {
        switch ($this->enabled) {
            case 0:
                return 4;
            case 1:
                // 按领取时间计算的一直有效
                if ($this->days > 0) {
                    return 2;
                }

                if ($this->effected_at > now()) {
                    return 1;
                }

                if ($this->effected_at <= now() && now() <= $this->expired_at) {
                    return 2;
                }

                return 3;
            default:
                return 3;
        }
    }

    /**
     * 获取优惠券状态
     * @return string
     */
    public function getCouponStatusNameAttribute(): string
    {
        switch ($this->enabled) {
            case 1:
                // 按领取时间计算的一直有效
                if ($this->days > 0) {
                    return '进行中';
                }

                if ($this->effected_at > now()) {
                    return '未开始';
                }

                if ($this->effected_at <= now() && now() <= $this->expired_at) {
                    return '进行中';
                }

                return '已失效';
            case 0:
                return '已作废';
            default:
                return '已失效';
        }
    }

    /**
     * 获取优惠券状态
     * @return int
     */
    public function getShareStatusAttribute(): int
    {
        if (now()->gt($this->share_end_at)) {
            return 0;
        }

        if ($this->share_count >= $this->share_total_count) {
            return 0;
        }

        return $this->getCouponStatusAttribute() === 2;
    }

    /**
     * 获得类型名
     *
     * @return string
     */
    public function getTypeNameAttribute()
    {
        switch ($this->coupon_type_id) {
            case self::VOUCHER:
                return '普通券';
            case self::TYPE_NEW_CUSTOM:
                return '用户福利';
            case self::TYPE_SHARED:
                return '用户抢券';
        }

        return '';
    }

    /**
     * 优惠券可用线路
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function usableLines()
    {
        return $this->belongsToMany(
            ExpressLineModel::class,
            'dsp_coupon_usable_lines',
            'coupon_id',
            'express_line_id'
        );
    }

    /**
     * 优惠券可用线路
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function usableCountries()
    {
        return $this->belongsToMany(
            Country::class,
            'jiyun_coupons_countries',
            'coupon_id',
            'country_id'
        );
    }

    public function template()
    {
        return $this->belongsTo(NewUserCouponTemplate::class,'template_id','id');
    }

    /**
     * @param Order $order
     * @return int
     */
    public function getAmount(Order $order)
    {
        if ($this->coupon_type_id === self::TYPE_PROPORTION) {
            return (int) bcmul($order->actual_payment_fee, $this->amount/ 100);
        }

        return $this->amount;
    }
}
