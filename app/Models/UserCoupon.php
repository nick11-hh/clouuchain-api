<?php

namespace App\Models;

use App\Events\CouponReceived;
use App\Models\Traits\Basis;
use App\Services\Client\StringTranslationService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * 用户优惠券
 */
class UserCoupon extends Model
{
    use Basis;

    protected $table = 'dsp_user_coupons';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'enabled' => 'bool',
    ];

    protected $appends = [
        'status',
        'can_use',
    ];

    /**
     * 优惠券
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id', 'id');
    }

    /**
     * 用户
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 计算优惠券状态
     * @throws InvalidArgumentException
     */
    public function getStatusAttribute()
    {
        if ($this->used_at) {
            return StringTranslationService::apiTran('已使用');
        }
        if (!$this->coupon || !$this->enabled) {
            return StringTranslationService::apiTran('已作废');
        }
        // 用户优惠券自身的生效时间和失效时间
        if ($this->effected_at > date('Y-m-d H:i:s', time())) {
            return StringTranslationService::apiTran('未开始');
        }
        if ($this->expired_at < date('Y-m-d H:i:s', time())) {
            return StringTranslationService::apiTran('已过期');
        }

        return StringTranslationService::apiTran('进行中');
    }

    /**
     * 计算优惠券状态
     */
    public function getStatusCodeAttribute()
    {
        if ($this->used_at) {
            return 1;
        }
        if (!$this->coupon || !$this->enabled) {
            return 3;
        }
        // 用户优惠券自身的生效时间和失效时间
        if ($this->effected_at > now()) {
            return 0;
        }
        if ($this->expired_at < now()) {
            return 2;
        }

        return 0;
    }

    /**
     * @return bool
     */
    public function getCanUseAttribute()
    {
        if (
            $this->used_at
            || (!$this->coupon
                || !$this->enabled)
            || ($this->effected_at && $this->effected > now())
            || ($this->expired_at && $this->expired_at < now())
        ) {
            return false;
        }

        if ($amount = request()->input('amount')) {
            if ($this->coupon->threshold > $amount) {
                return false;
            }
        }

        if ($id = request()->input('express_line_id')) { //如果指定了线路
            if (!in_array($id, $this->coupon->usableLines->modelKeys())
                && $this->coupon->scope === Coupon::SCOPE_SPECIFY_LINES) {
                return false;
            }
        }

        if ($id = request()->input('country_id')) { //如果指定了线路
            if (!in_array($id, $this->coupon->usableCountries->modelKeys())
                && $this->coupon->scope === Coupon::SCOPE_SPECIFY_COUNTRIES) {
                return false;
            }
        }

        return true;
    }

    /**
     * 优惠券订单
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_number', 'order_sn');
    }

    /**
     *  获取可用优惠券
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUsable($query)
    {
        $query = $query->whereNull('used_at')->where('enabled', 1);

        $query = $query->where(function ($builder) {
            $builder->where('effected_at', '<', now())
                ->where('expired_at', '>', now())
                ->whereHas('coupon', function ($builder) {
                    $builder->where('enabled', 1);
                });
        });

        return $query->whereDoesntHave('order', function ($builder) {
            $builder->where('status', Order::WAIT_CHECK);
        });
    }

    /**
     *  获取不可用优惠券
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnAvailable($query, $amount = 0, $expressLineId = null)
    {
        $query = $query->whereNotNull('used_at');

        $query->orWhere(function ($builder) use ($amount, $expressLineId) {
            $builder->where('effected_at', '>', now())
                ->orWhere('expired_at', '<', now())
                ->orWhereHas('coupon', function ($builder) use ($amount, $expressLineId) {
                    $builder->where('enabled', 0);

                    if ($amount) {
                        $builder->orWhere('threshold', '>', $amount);
                    }
                    if ($expressLineId) {
                        $builder->orWhereHas('usableLines', function ($lines) use ($expressLineId) {
                            $lines->whereKeyNot($expressLineId);
                        });
                    }
                });
        });

        return $query->orWhere('enabled', 0);
    }

    /**
     * 根据传入的参数获取优惠券列表
     */
    public static function getCouponList($params)
    {
        $couponList = new self();

        if ($params['user_id']) { // 只查询指定用户的优惠券
            $couponList = $couponList->where('user_id', $params['user_id']);
        }

        $params['available'] = $params['available'] ?? 0;
        // 已使用的优惠券不再显示
        if ($params['available'] != 3) {
            $couponList = $couponList->whereNull('used_at');
        }

        // 即将过期。已过期
        if (!empty($params['expired_date'])) {
            $couponList->where('enabled', 1);
            $couponList->with(['coupon'])->where(function ($query) use ($params) {
                $query->where('expired_at', '>', Carbon::parse($params['expired_date'])->startOfDay());
                if (!empty($params['expired_end_date'])) {
                    $query->where('expired_at', '<', Carbon::parse($params['expired_end_date'])->startOfDay());
                }
            });
        }

        switch ($params['available']) {
            case 0:
                break;
            case 1:
                $couponList = $couponList->usable();

                /** @var LengthAwarePaginator $coupons */
                $coupons = $couponList->with('coupon.usableLines', function ($query) {
                    $query->selectRaw('dsp_express_line.id as id, name, name as cn_name, name as en_name');
                })->with('coupon.usableCountries', function ($query) {
                    $query->selectRaw('dsp_country.id as id, name');
                })->whereHas('coupon', function ($query) use ($params) {
                    if ($params['amount'] ?? 0) {
                        $query->where('threshold', '<=', $params['amount']);
                    }

                    if ($params['weight'] ?? 0) {
                        $query->where('min_weight', '<=', $params['weight']);
                    }

                    if ($params['express_line_id'] ?? null) { //如果指定了线路
                        $query->where(function ($q) use ($params) {
                            $q->where('scope', '!=', Coupon::SCOPE_SPECIFY_LINES)
                                ->orWhereHas('usableLines', function ($q) use ($params) {
                                    $q->whereKey($params['express_line_id']);
                                });
                        });
                    }
                    //如果指定了国家
                    if ($params['country_id'] ?? null) {
                        $query->where(function ($q) use ($params) {
                            $q->where('scope', '!=', Coupon::SCOPE_SPECIFY_COUNTRIES)
                                ->orWhereHas('usableCountries', function ($q) use ($params) {
                                    $q->whereKey($params['country_id']);
                                });
                        });
                    }
                })->pageSize();

                return $coupons->through(function ($coupon) use ($params) {
                    $coupon['order_can_use'] = true;

                    if ($params['amount'] ?? 0) {
                        if ($coupon->coupon->threshold > $params['amount']) {
                            $coupon['order_can_use'] = false;
                            $coupon['can_use'] = false;
                        }
                    }

                    if ($params['express_line_id'] ?? null) { //如果指定了线路
                        if (!in_array($params['express_line_id'], $coupon->coupon->usableLines->modelKeys())
                            && $coupon->coupon->scope === Coupon::SCOPE_SPECIFY_LINES) {
                            $coupon['order_can_use'] = false;
                            $coupon['can_use'] = false;
                        }
                    }

                    $coupon['coupon']['expired_at'] = $coupon['expired_at'];
                    $coupon['coupon']['effected_at'] = $coupon['effected_at'];

                    return $coupon;
                });
            case 2:
                $couponList = $couponList->unAvailable($params['amount'] ?? 0, $params['express_line_id'] ?? null);
                break;
            case 3:
                $couponList = $couponList->whereNotNull('used_at');
                break;
            case 4:
                $couponList = $couponList->usable();

                /** @var LengthAwarePaginator $coupons */
                $coupons = $couponList->with('coupon.usableLines', function ($query) {
                    $query->selectRaw('dsp_express_line.id as id, name, name as cn_name, name as en_name');
                })->with('coupon.usableCountries', function ($query) {
                    $query->selectRaw('dsp_country.id as id, name');
                })->pageSize();

                return $coupons->through(function ($coupon) use ($params) {
                    $coupon['order_can_use'] = true;

                    if ($params['amount'] ?? 0) {
                        if ($coupon->coupon->threshold > $params['amount']) {
                            $coupon['order_can_use'] = false;
                            $coupon['can_use'] = false;
                        }
                    }

                    if ($params['weight'] ?? 0) {
                        if ($coupon->coupon->weight > $params['amount']) {
                            $coupon['order_can_use'] = false;
                            $coupon['can_use'] = false;
                        }
                    }

                    if ($params['express_line_id'] ?? null) { //如果指定了线路
                        if (!in_array($params['express_line_id'], $coupon->coupon->usableLines->modelKeys())
                            && $coupon->coupon->scope === Coupon::SCOPE_SPECIFY_LINES) {
                            $coupon['order_can_use'] = false;
                            $coupon['can_use'] = false;
                        }
                    }
                    //如果指定了国家
                    if ($params['country_id'] ?? null) {
                        if (!in_array($params['country_id'], $coupon->coupon->usableCountries->modelKeys())
                            && $coupon->coupon->scope === Coupon::SCOPE_SPECIFY_COUNTRIES) {
                            $coupon['order_can_use'] = false;
                            $coupon['can_use'] = false;
                        }
                    }

                    $coupon['coupon']['expired_at'] = $coupon['expired_at'];
                    $coupon['coupon']['effected_at'] = $coupon['effected_at'];

                    return $coupon;
                });
            default:
                # code...
                break;
        }

        $couponList = $couponList->with('coupon.usableLines', function ($query) {
            $query->selectRaw('dsp_express_line.id as id, name, name as cn_name, name as en_name');
        });

        $data = $couponList->pageSize();

        return $data->through(function (UserCoupon $userCoupon) {
            $userCoupon['coupon']['expired_at'] = $userCoupon['expired_at'];
            $userCoupon['coupon']['effected_at'] = $userCoupon['effected_at'];
            $userCoupon->append('status_code');

            return $userCoupon;
        });
    }

    /**
     * 根据传入的参数获取可用优惠券的数量
     */
    public static function getUsableCouponCount(array $params)
    {
        $couponList = new self();

        $coupons = $couponList
            ->where('user_id', $params['user_id'])
            ->whereNull('used_at')
            ->with(['coupon.usableLines', 'coupon.usableCountries'])
            ->usable()
            ->get();

        $all = $coupons->count();

        $usable =  $coupons->filter(function ($coupon) use ($params) {
            if ($params['amount'] ?? 0) {
                if ($coupon->coupon->threshold > $params['amount']) {
                    return false;
                }
            }
            if ($params['weight'] ?? 0) {
                if ($coupon->coupon->min_weight > $params['weight']) {
                    return false;
                }
            }
            // 如果指定了线路
            if ($params['express_line_id'] ?? null) {
                if (!in_array($params['express_line_id'], $coupon->coupon->usableLines->modelKeys())
                    && $coupon->coupon->scope === Coupon::SCOPE_SPECIFY_LINES) {
                    return false;
                }
            }
            // 如果指定了线路
            if ($params['country_id'] ?? null) {
                if (!in_array($params['country_id'], $coupon->coupon->usableCountries->modelKeys())
                    && $coupon->coupon->scope === Coupon::SCOPE_SPECIFY_COUNTRIES) {
                    return false;
                }
            }

            return true;
        })->count();

        return compact('all', 'usable');
    }

    protected static function boot()
    {
        parent::boot();

        // static::created(function (self $model) {
        //     event(new CouponReceived($model));
        // });
    }
}
