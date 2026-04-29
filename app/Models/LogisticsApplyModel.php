<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;

class LogisticsApplyModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_logistics_apply';

    protected $casts = [
        'shipper_boxs' => 'array',
    ];

    /**
     * 更换物流原因：1-首次申请 2-修改运单信息 3-订单拆分 4-订单合并 5-更换物流渠道 6-其他
     */
    public const CHANGE_TYPE_FIRST = 1;
    public const CHANGE_TYPE_EDIT = 2;
    public const CHANGE_TYPE_SPLIT = 3;
    public const CHANGE_TYPE_MERGE = 4;
    public const CHANGE_TYPE_REPLACE = 5;
    public const CHANGE_TYPE_OTHER = 6;

    public const TRACKING_PLATFORM_YAOQI = '17track';

    public static function changeTypeList(): array
    {
        return [
            self::CHANGE_TYPE_FIRST => __('首次申请运单'),
            self::CHANGE_TYPE_EDIT => __('修改申请运单信息'),
            self::CHANGE_TYPE_SPLIT => __('订单拆分包裹'),
            self::CHANGE_TYPE_MERGE => __('订单合并包裹'),
            self::CHANGE_TYPE_REPLACE => __('更换物流渠道'),
            self::CHANGE_TYPE_OTHER => __('其他原因'),
        ];
    }



    /**
     * PKG+年月日+订单后五位+更换类型字母+自增两位数字
     */
    public static function getPackageSn(): string
    {
        $prefix = 'EXP';
        if (Cache::has('generateApplyPackageSn')) {
            $increment = Cache::increment('generateApplyPackageSn');
        } else {
            $latest = self::query()->latest('id')->first();
            if (empty($latest)) {
                $increment = now()->unix();
            } else {
                $increment = substr($latest->package_sn, -10);
                // 做个判断兼容老数据
                if (strlen($increment) < 6) {
                    $increment = now()->unix();
                } else {
                    $increment ++;
                }

            }
            Cache::set('generateApplyPackageSn', $increment);
        }
        return $prefix . $increment;
    }
}
