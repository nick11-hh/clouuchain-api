<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class OutboundOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_outbound_orders';

    protected $guarded = [];

    protected $casts = [];

    CONST STATUS_WAIT_PICKING = 1;
    CONST STATUS_WAIT_SECOND_PICK = 2;
    CONST STATUS_WAIT_PACK = 3;
    CONST STATUS_WAIT_OUTBOUND= 4;
    CONST STATUS_OUTBOUND = 5;
    CONST STATUS_CANCEL = 99;

    CONST TYPE_SIGN_SKU = 1;
    CONST TYPE_SIGN_SKU_MULTIPLE = 2;
    CONST TYPE_MULTIPLE_SKU = 3;

    public static function statusList()
    {
        return [
            self::STATUS_WAIT_PICKING => __('待拣货'),
            self::STATUS_WAIT_SECOND_PICK => __('待分拣'),
            self::STATUS_WAIT_PACK => __('待打包'),
            self::STATUS_WAIT_OUTBOUND => __('待出库'),
            self::STATUS_OUTBOUND => __('已出库'),
            self::STATUS_CANCEL => __('已取消'),
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

    public function items()
    {
        return $this->hasMany(OutboundOrderItem::class, 'outbound_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(WarehouseAddress::class, 'warehouse_id', 'id');
    }

    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id');
    }

    public function address()
    {
        return $this->hasOne(OutboundOrderAddress::class, 'outbound_id', 'id');
    }

    public function package()
    {
        return $this->hasOne(Package::class, 'id', 'package_id');
    }



    public function getStatusNameAttribute()
    {
        return self::statusList()[$this->status] ?? '-';
    }

    public function getTypeNameAttribute()
    {
        return self::typeList()[$this->type] ?? '-';
    }

    public static function init($params, $opt = 1)
    {
        $data = [
            'custom_id' => $params['custom_id'],
            'warehouse_id' => $params['warehouse_id'],
            'type' => $params['type'] ?? 1,
            'logistics_provider' => $params['logistics_provider'],
            'sale_platform' => $params['sale_platform'],
            'tracking_number' => $params['tracking_number'],
            'shipment_pdf' => $params['shipment_pdf'],
            'remark' => $params['remark'] ?? '',
            'package_id' => $params['package_id'] ?? 0
        ];
        if ($opt == 1) {
            $data['outbound_sn'] = self::getOutboundOrderSn();
        }
        return $data;
    }

    public static function getOutboundOrderSn(): string
    {
        $pre = 'OB';
        if (Cache::has('outboundOrderSn')) {
            $increment = Cache::increment('outboundOrderSn');
        } else {
            $latest = self::query()->latest('id')->first();
            if (empty($latest)) {
                $increment = now()->unix();
            } else {
                $increment = substr($latest->outbound_sn, -10);
                $increment ++;
            }
            Cache::increment('outboundOrderSn', $increment);
        }
        return $pre. $increment;
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(
            Order::class,
            'dsp_outbound_shop_order_relates',
            'outbound_order_id',
            'shop_order_id'
        );
    }
}
