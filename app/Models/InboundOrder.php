<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class InboundOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_inbound_orders';

    protected $guarded = [];

    CONST STATUS_WAIT_INBOUND = 1;
    CONST STATUS_RECEIVING = 2;
    CONST STATUS_RECEIVED = 3;
    CONST STATUS_IN_STOCK = 4;
    CONST STATUS_CANCEL = 5;

    //入库单类型
    CONST TYPE_STOCK = 1;//备货入库
    CONST TYPE_PURCHASE = 2;//采购入库
    CONST TYPE_OTHER = 9;//其他入库

    // 入库单来源
    const ORDER_SOURCE_FOR_SYSTEM_ADD = 1; //系统添加
    const ORDER_SOURCE_FOR_CUSTOMER_BUY = 2; //客户下单购买

    public static function statusList()
    {
        return [
            self::STATUS_WAIT_INBOUND => __('待入库'),
            self::STATUS_RECEIVING => __('收货中'),
            self::STATUS_RECEIVED => __('已收货'),
            self::STATUS_IN_STOCK => __('已上架'),
            self::STATUS_CANCEL => __('已取消'),
        ];
    }

    public static function typeList()
    {
        return [
            self::TYPE_STOCK => __('备货入库'),
            self::TYPE_PURCHASE => __('采购入库'),
            self::TYPE_OTHER => __('其他入库'),
        ];
    }

    public function items()
    {
        return $this->hasMany(InboundOrderItem::class, 'inbound_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(WarehouseAddress::class, 'warehouse_id', 'id');
    }

    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id');
    }

    public function purchase()
    {
        return $this->belongsToMany(PurchaseOrdersModel::class, 'dsp_purchase_inbound_relation', 'inbound_id', 'purchase_id');
    }

    public function getStatusNameAttribute()
    {
        return self::statusList()[$this->status] ?? '-';
    }

    public function getInboundTypeNameAttribute()
    {
        return self::typeList()[$this->inbound_type] ?? '-';
    }

    public static function init($params, $opt = 1)
    {
        $data = [
            'custom_id' => $params['custom_id'] ?? 0,
            'warehouse_id' => $params['warehouse_id'],
            'logistics_sn' => $params['logistics_sn'] ?? '',
            'expect_time' => $params['expect_time'] ?? null,
            'remark' => $params['remark'] ?? '',
            'stock_order_sn' => $params['stock_order_sn'] ?? '',//备货单号 来源：备货订单-设为已采购
            'order_source' => $params['order_source'] ?? self::ORDER_SOURCE_FOR_SYSTEM_ADD,
        ];
        if ($opt == 1) {
            $data['inbound_sn'] = self::getInboundSn();
            $data['inbound_type'] = $params['inbound_type'] ?? self::TYPE_STOCK;
        }
        return $data;
    }

    public static function getInboundSn(): string
    {
        $pre = 'IB';
        if (Cache::has('inboundOrderSn')) {
            $increment = Cache::increment('inboundOrderSn');
        } else {
            $latest = self::query()->latest('id')->first();
            if (empty($latest)) {
                $increment = now()->unix();
            } else {
                $increment = substr($latest->inbound_sn, -10);
                $increment ++;
            }
            Cache::increment('inboundOrderSn', $increment);
        }
        return $pre . $increment;
    }

}
