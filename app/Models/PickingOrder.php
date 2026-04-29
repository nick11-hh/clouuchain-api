<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class PickingOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_picking_orders';

    protected $guarded = [];

    protected $casts = [];

    CONST STATUS_WAIT_PICKING = 1;
    CONST STATUS_PICKING = 2;
    CONST STATUS_FINISH = 3;
    CONST STATUS_ABNORMAL = 99;

    CONST TYPE_SIGN_SKU = 1;
    CONST TYPE_SIGN_SKU_MULTIPLE = 2;
    CONST TYPE_MULTIPLE_SKU = 3;

    public static function statusList()
    {
        return [
            self::STATUS_WAIT_PICKING => __('待拣货'),
            self::STATUS_PICKING => __('拣货中'),
            self::STATUS_FINISH => __('已拣货'),
            self::STATUS_ABNORMAL => __('拣货异常'),
        ];
    }

    public static function typeList()
    {
        return [
            self::TYPE_SIGN_SKU => __('单sku单件'),
            self::TYPE_SIGN_SKU_MULTIPLE => __('单sku多件'),
            self::TYPE_MULTIPLE_SKU => __('多sku多件'),
        ];
    }

    public function outboundOrders()
    {
        return $this->hasMany(OutboundOrder::class, 'picking_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(WarehouseAddress::class, 'warehouse_id', 'id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'picking_staff_id', 'id');
    }


    public function getStatusNameAttribute()
    {
        return self::statusList()[$this->status] ?? '-';
    }

    public function getTypeNameAttribute()
    {
        return self::typeList()[$this->type] ?? '-';
    }

    public static function init($params)
    {
        return [
            'warehouse_id' => $params['warehouse_id'],
            'picking_sn' => self::getPickingOrderSn(),
            'type' => $params['type'],
            'picking_staff_id' => $params['picking_staff_id'] ?? 0,
        ];
    }


    public static function getPickingOrderSn(): string
    {
        $pre = 'PK';
        if (Cache::has('pickingOrderSn')) {
            $increment = Cache::increment('pickingOrderSn');
        } else {
            $latest = self::query()->latest('id')->first();
            if (empty($latest)) {
                $increment = now()->unix();
            } else {
                $increment = substr($latest->picking_sn, -10);
                $increment ++;
            }
            Cache::increment('pickingOrderSn', $increment);
        }
        return $pre. $increment;
    }
}
