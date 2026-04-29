<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Package extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_package';

    protected $guarded = [];

    const STATUS_WAIT_DEAL = 1;
    const STATUS_DISTRIBUTION = 2;
    const STATUS_OUTBOUND = 3;
    const STATUS_DELIVERED = 4;
    const STATUS_CANCELED = 99;

    const STATUS_LIST = [
        self::STATUS_WAIT_DEAL => '待处理',
        self::STATUS_DISTRIBUTION => '配货中',
        self::STATUS_OUTBOUND => '已出库',
        self::STATUS_DELIVERED => '已妥投',
        self::STATUS_CANCELED => '取消',
    ];


    const LOGISTICS_WAIT_APPLY = 'wait';
    const LOGISTICS_PROGRESSED = 'progressed';
    const LOGISTICS_APPLY_SUCCESS = 'success';
    const LOGISTICS_APPLY_FAILURE = 'failure';
    const LOGISTICS_STATUS_LIST = [
        self::LOGISTICS_WAIT_APPLY => '待申请',
        self::LOGISTICS_PROGRESSED => '申请中',
        self::LOGISTICS_APPLY_SUCCESS => '申请成功',
        self::LOGISTICS_APPLY_FAILURE => '申请失败',
    ];

    const STOCK_WAIT = 'wait';
    const STOCK_SUCCESS = 'success';
    const STOCK_LACK = 'lack';
    const STOCK_STATUS_LIST = [
        self::STOCK_WAIT => '待配货',
        self::STOCK_SUCCESS => '有货',
        self::STOCK_LACK => '缺货',
    ];

    const PACKAGE_DEFAULT = 1;
    const PACKAGE_SPLIT = 2;
    const PACKAGE_MERGE = 3;
    const PACKAGE_SPLIT_MERGE_STATUS = [
        self::PACKAGE_DEFAULT => '未拆合',
        self::PACKAGE_SPLIT => '拆包',
        self::PACKAGE_MERGE => '合包'
    ];

    public function items()
    {
        return $this->hasMany(PackageItem::class, 'package_id', 'id');
    }

    public function packageAddress()
    {
        return $this->hasOne(PackageAddress::class, 'package_id', 'id');
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'dsp_package_items', 'package_id', 'shop_order_id', 'id', 'id')->distinct();
    }

    public function logisticsApply()
    {
        return $this->hasOne(LogisticsApplyModel::class, 'package_id', 'id')->latest();
    }

    public function logisticsApplies()
    {
        return $this->hasMany(LogisticsApplyModel::class, 'package_id', 'id');
    }

    public function expressCompany()
    {
        return $this->belongsTo(CompanyExpressModel::class, 'express_companies_code', 'code');
    }

    public function subPackage()
    {
        return $this->hasMany(Package::class, 'source_id', 'id')->withTrashed();
    }

    public function outboundOrder()
    {
        return $this->hasOne(OutboundOrder::class, 'package_id', 'id')->where('status', '!=', OutboundOrder::STATUS_CANCEL);
    }

    public static function init($params)
    {
        return [
            'package_sn'             => self::generatePackageSn(),
            'order_id'               => $params['order_id'] ?? 0,
            'express_companies_id'   => $params['express_companies_id'],
            'express_companies_code' => $params['express_companies_code'],
            'express_channel_code'   => $params['express_channel_code'],
            'status'                 => 1,
            'split_merge_status'     => $params['split_merge_status'] ?? self::PACKAGE_DEFAULT
        ];
    }

    public static function generatePackageSn()
    {
        $prefix = 'PKG';
        if (Cache::has('generatePackageSn')) {
            $increment = Cache::increment('generatePackageSn');
        } else {
            $latest = self::query()->latest('id')->first();
            if (empty($latest)) {
                $increment = now()->unix();
            } else {
                $increment = substr($latest->package_sn, -10);
                $increment ++;
            }
            Cache::increment('generatePackageSn', $increment);
        }
        return $prefix . $increment;
    }

}
