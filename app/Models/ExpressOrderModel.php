<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class ExpressOrderModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_express_order';

    protected $guarded = [];

    /**
     * 状态 0-申请中 1申请成功 2-申请失败
     */
    public const STATUS_APPLY = 0;
    public const STATUS_SUCCESS = 1;
    public const STATUS_FAILURE = 2;

    /**
     * 更换物流原因：1-首次申请 2-修改运单信息 3-订单拆分 4-订单合并 5-更换物流渠道 6-其他
     */
    public const CHANGE_TYPE_FIRST = 1;
    public const CHANGE_TYPE_EDIT = 2;
    public const CHANGE_TYPE_SPLIT = 3;
    public const CHANGE_TYPE_MERGE = 4;
    public const CHANGE_TYPE_REPLACE = 5;
    public const CHANGE_TYPE_OTHER = 6;

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

    public static function getChangeTypeName($changeType)
    {
        return self::changeTypeList()[$changeType] ?? '';
    }

    public function getChangeTypeAttribute()
    {
        $list = self::changeTypeList();

        // return $list[$this->change_type];
    }

    public function items()
    {
        return $this->hasMany(ExpressOrderItemsModel::class, 'express_order_id', 'id');
    }

    public function address()
    {
        return $this->hasOne(ExpressOrderAddressModel::class, 'express_order_id','id');
    }

    public function shopOrders()
    {
        return $this->belongsToMany(Order::class, 'dsp_shop_order_express_order_mappings', 'express_order_id', 'shop_order_id');
    }

    public function logistics()
    {
        return $this->hasOne(LogisticsApplyModel::class, 'express_order_id','id');
    }

    public function tracking()
    {
        return $this->hasOne(ExpressOrderTrackingModel::class, 'express_order_id','id');
    }

    public static function init($params, $type = 'create'): array
    {
        $data = [
            'express_companies_id' => $params['express_companies_id'] ?? 0, //物流公司ID
            'express_companies_code' => $params['express_companies_code'] ?? 0, //物流公司编码
            'logistics_channel_id' => $params['logistics_channel_id'] ?? 0, //物流渠道ID
            'express_line_id' => $params['express_line_id'] ?? 0, //渠道路线ID
            'warehouse_id' => $params['warehouse_id'] ?? 0, //仓库ID
            'package_weight' => $params['package_weight'] ?? 0, //包裹重量(g)
            'length' => $params['length'] ?? 0, //包裹长(cm)
            'width' => $params['width'] ?? 0, //包裹宽(cm)
            'height' => $params['height'] ?? 0, //包裹高(cm)
            'package_count' => $params['package_count'] ?? 1, //包裹数量
            'change_type' => $params['change_type'] ?? 1, //更换物流原因：1-首次申请 2-修改运单信息 3-订单拆分 4-订单合并 5-更换物流渠道 6-其他
            'change_remark' => $params['change_remark'] ?? '', //更换物流备注
        ];

        if ($type === 'create') {
            $data['package_sn'] = self::getPackageSn($params); //包裹号
            $data['status'] = $params['status'] ?? 0; //订单状态：0-申请中 1-申请成功 2-申请失败',
        }
        return $data;
    }

    /**
     * PKG+年月日+订单后五位+更换类型字母+自增两位数字
     */
    public static function getPackageSn($params): string
    {
        $pre = 'PKG';

        $changeType = match ($params['change_type']) {
            self::CHANGE_TYPE_EDIT => 'E',
            self::CHANGE_TYPE_SPLIT => 'S',
            self::CHANGE_TYPE_MERGE => 'M',
            self::CHANGE_TYPE_REPLACE => 'R',
            self::CHANGE_TYPE_OTHER => 'O',
            default => 'F',
        };

        $orderSn = random_int(10000, 99999);
        if ($params['order_sn'] ?? '') {
            $orderSn = substr($params['order_sn'], -5);
        }

        $date = date('ymd');
        $cacheKey = 'ExpressOrderPackageSn_' . $date;

        if (Cache::has($cacheKey)) {
            $increment = Cache::increment($cacheKey);
        } else {
            // 获取当前的时间戳（秒）
            $currentTimestamp = time();

            // 获取今天的结束时间戳（秒）
            $todayEndTimestamp = strtotime("tomorrow") - 1;

            // 计算剩余的秒数
            $secondsLeft = $todayEndTimestamp - $currentTimestamp;

            $increment = 1;
            Cache::set($cacheKey, $increment, $secondsLeft);
        }

        $number = str_pad($increment, 2, 0, STR_PAD_LEFT);

        return $pre . $date . $orderSn . $changeType . $number;
    }

}
