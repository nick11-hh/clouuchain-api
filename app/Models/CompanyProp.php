<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\GetName;
use App\Models\Traits\HasValidateUnique;
use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class CompanyProp extends Model
{
    use Basis, GetName, HasValidateUnique;

    public const SHARE_IMG = 1; // 分享图片
    public const PAY_QRCODE = 2; // 支付二维码

    public const PC_ORDER = 3; // pc 下单端域名
    public const PC_INDEX = 4; // pc 首页端域名
    public const H5_ORDER = 5; // h5 下单端域名
    public const MINI_PATH = 6; // 小程序分享 path

    public const USER_DEFAULT_PHONE = 5; // 新注册用户的默认手机号

    public const PACKAGE_EXPRESS_LINE = 8; // 包裹预报阶段选择线路

    public const PACKAGE_IN_STORAGE_SIZE = 9; // 包裹预报阶段需要填写尺寸
    public const PACKAGE_IN_STORAGE_LOCATION = 10; // 包裹预报阶段需要填写货位

    public const SHOULD_VALIDATE_PHONE = 11; // 用户要验证手机号
    public const SHOULD_VALIDATE_EMAIL = 12; // 用户要验证邮箱

    public const SHOULD_UPDATE_PROFILE = 13; // 用户要更新用户个人信息
    // 用户要更新用户个人信息
    public const PACKAGE_WARNING = 14;

    public const BALANCE_PAY = 15;

    public const PROP_TYPE = 16;

    public const PACKAGE_AUTO_CODE = 17;
    public const CUSTOM_LOCATION = 18;
    public const WITHDRAW_WECHAT_BALANCE = 19; // 开启佣金提现到微信余额
    public const SHOULD_LOGIN_COUPON_STATUS = 20;
    public const PACKAGE_TRACK_WITH_ORDER = 21; // 包裹跟踪时是否包含订单物流
    public const DISABLE_NOT_CONFIRMED = 22; // 包裹跟踪时是否包含订单物流
    public const NO_OWNER_PACKAGE_DISMISS_LENGTH = 23; // 无人认领包裹隐藏单号长度
    public const ENABLE_PACKAGE_SPU = 24; // 包裹SPU集包
    public const ALLOW_UNPAID_SIGN = 25; // 包裹SPU集包
    public const ORDER_PACK_BY_SAVE = 26; // 工作量计算是否只看保存
    public const SHIPMENT_DISMISS_AMOUNT = 27; // 发货单隐藏金额
    public const DISABLE_SIGN_BEFORE_STATION = 28; // 站点之前的订单不允许客户签收
    public const STRICT_CONFIRMED = 29; // 严格的待确认模式
    public const AUTO_SETTLE_COMMISSION = 30; // 自动结算佣金的时间
    public const REUSE_OLD_LOCATION = 31; // 重复使用旧货位
    public const DISABLE_RE_ADD_SHIPMENT = 32; // 禁止重新添加到发货单
    public const DISABLE_UPDATE_IN_STORAGE_TIME = 33; // 关闭更新入库时间
    public const RESERVE_BOX_PACKAGE_PACKED = 34;         //预留箱号是否只能装已集包的包裹
    public const ORDER_INVOICE_MODE = 35;         //预留箱号是否只能装已集包的包裹

    protected $table = 'dsp_company_prop';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    /**
     * @param int $companyId
     * @return int
     */
    public static function shouldValidatePhone(int $companyId): int
    {
        $prop = self::query()->where(
            [
                ['type', '=', CompanyProp::SHOULD_VALIDATE_PHONE],
                ['company_id', '=', $companyId],
            ]
        )->select('prop')->first();

        return (int) ($prop ? $prop->prop : 0);
    }

    /**
     * @param int $companyId
     * @return int
     */
    public static function shouldValidateEmail(int $companyId): int
    {
        $prop = self::query()->where(
            [
                ['type', '=', CompanyProp::SHOULD_VALIDATE_EMAIL],
                ['company_id', '=', $companyId],
            ]
        )->select('prop')->first();

        return (int) ($prop ? $prop->prop : 0);
    }

    /**
     * @param int $companyId
     * @return int
     */
    public static function spuEnabled(int $companyId): int
    {
        $prop = self::query()->where(
            [
                ['type', '=', CompanyProp::ENABLE_PACKAGE_SPU],
                ['company_id', '=', $companyId],
            ]
        )->select('prop')->first();

        return (int) ($prop ? $prop->prop : 0);
    }

    /**
     * @return int
     */
    public static function shouldValidatePackageLocation(): int
    {
        $prop = self::query()->where(
            [
                ['type', '=', CompanyProp::PACKAGE_IN_STORAGE_LOCATION]
            ]
        )->select('prop')->first();

        return (int) ($prop ? $prop->prop : 0);
    }

    /**
     * @return int
     */
    public static function shouldValidatePackageSize(): int
    {
        $prop = self::query()->where(
            [
                ['type', '=', CompanyProp::PACKAGE_IN_STORAGE_SIZE]
            ]
        )->select('prop')->first();

        return (int) ($prop ? $prop->prop : 0);
    }

    /**
     * @return int
     */
    public static function packagePropType(): int
    {
        $prop = self::query()->where(
            [
                ['type', '=', CompanyProp::PROP_TYPE]
            ]
        )->select('prop')->first();

        return (int) ($prop ? $prop->prop : 0);
    }

    /**
     * @param int $prop
     * @return int
     */
    public static function getValue(int $prop): int
    {
        $key = sprintf('CompanyPropData_%s', self::getCompanyId());

        $data = Cache::get($key, function () use ($key) {
            $data = self::all();

            Cache::put($key, $data, CarbonInterval::hours(6));

            return $data;
        });

        return (int) ($data->firstWhere('type', $prop)?->prop ?? 0);
    }

    /**
     * @param int $type
     * @return mixed
     */
    public static function getOriginValue(int $type)
    {
        $type = CompanyProp::query()->where('type', $type)->first();

        return $type?->prop;
    }

    /**
     * @return bool
     */
    public static function disableSignBeforeStation(): bool
    {
        $prop = CompanyProp::query()
            ->where('type',CompanyProp::DISABLE_SIGN_BEFORE_STATION)
            ->select('prop')
            ->first();

        if ($prop && $prop->prop) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public static function disableUpdateInStorageTime(): bool
    {
        if (self::getValue(self::DISABLE_UPDATE_IN_STORAGE_TIME)) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public static function disableReAddShipment(): bool
    {
        $prop = CompanyProp::query()
            ->where('type',CompanyProp::DISABLE_RE_ADD_SHIPMENT)
            ->select('prop')
            ->first();

        if ($prop && $prop->prop) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public static function enableReuseOldLocation(): bool
    {
        $prop = CompanyProp::query()
            ->where('type',CompanyProp::REUSE_OLD_LOCATION)
            ->select('prop')
            ->first();

        if ($prop && $prop->prop) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public static function enableOrderInvoice(): bool
    {
        $prop = CompanyProp::query()
            ->where('type',CompanyProp::ORDER_INVOICE_MODE)
            ->select('prop')
            ->first();

        if ($prop && $prop->prop) {
            return true;
        }

        return false;
    }

    /**
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($model) {
            $data = self::all();

            Cache::forever(sprintf('CompanyPropData_%s', self::getCompanyId()), $data);
        });
    }
}
