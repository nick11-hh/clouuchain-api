<?php

namespace App\Models;

use App\Casts\ImageUrl;
use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use App\Models\Traits\WarehouseFilter;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

/**
 * 发货单
 * Class Shipment
 * @package App\Models
 */
class Shipment extends Model
{
    use Basis,
        HasValidateUnique,
        WarehouseFilter;

    //状态
    public const STATUS_UN_SHIPPED = 0; //未发货
    public const STATUS_SHIPPED = 1; //已发货

    public const TYPE_NORMAL = 0;
    public const TYPE_AIR = 1;
    public const TYPE_SHIP = 2;
    public const TYPE_OTHERS = 3;

    public const PRINT_STATUS_UN_PRINT = 0;
    public const PRINT_STATUS_PRINTED = 1;

    protected $table = 'dsp_shipments';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'image' => ImageUrl::class
    ];

    protected $dates = [
        'shipped_at',
    ];

    /**
     * 发货单下的订单
     * @return BelongsToMany
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'dsp_shipment_orders', 'shipment_id', 'order_id');
    }

    /**
     * 目的国
     * @return HasOne
     */
    public function country(): HasOne
    {
        return $this->hasOne(Country::class, 'id', 'destination_country_id');
    }

    /**
     * 发货单物品属性
     * @return BelongsToMany
     */
    public function props(): BelongsToMany
    {
        return $this->belongsToMany(
            PackageProp::class,
            'dsp_shipment_props',
            'shipment_id',
            'prop_id',
            'id',
            'id'
        );
    }

    /**
     * 所属仓库
     *
     * @return BelongsTo
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(WarehouseAddress::class, 'warehouse_id', 'id');
    }

    /**
     * 轨迹信息
     *
     * @return HasMany
     */
    public function logistics(): HasMany
    {
        return $this->hasMany(ShipmentLogistics::class, 'shipment_id', 'id');
    }

    /**
     * 订单发货快递公司
     *
     * @return HasOne
     */
    public function express()
    {
        return $this->hasOne(CompanyExpress::class, 'code', 'logistics_company');
    }

    /**
     * @return HasOne
     */
    public function airInfo()
    {
        return $this->hasOne(ShipmentAirInfo::class, 'shipment_id', 'id');
    }

    /**
     * @return HasOne
     */
    public function shipInfo()
    {
        return $this->hasOne(ShipmentShipInfo::class, 'shipment_id', 'id');
    }

    /**
     * @return HasOne
     */
    public function containerInfo()
    {
        return $this->hasOne(ShipmentContainerInfo::class, 'shipment_id', 'id');
    }

    /**
     * @return HasOne
     */
    public function extraInfo()
    {
        return $this->hasOne(ShipmentExtraInfo::class, 'shipment_id', 'id');
    }

    /**
     * @return HasOne
     */
    public function senderInfo()
    {
        return $this->hasOne(ShipmentSenderInfo::class, 'shipment_id', 'id');
    }

    /**
     * @return HasMany
     */
    public function orderBoxes()
    {
        return $this->hasMany(OrderBox::class, 'shipment_id', 'id');
    }

    /**
     * @return HasOne
     */
    public function station()
    {
        return $this->hasOne(SelfPickupStation::class, 'id', 'station_id');
    }

    /**
     * @return HasOne
     */
    public function stationInfo()
    {
        return $this->hasOne(ShipmentStationInfo::class, 'shipment_id', 'id');
    }

    /**
     * @return mixed
     */
    public function getStatusNameAttribute()
    {
        return $this->logistics->sortByDesc('id')->first()->context
            ?? ($this->status === self::STATUS_UN_SHIPPED ? '未发货' : '已发货');
    }

    /**
     * 创建一个新的发货单号
     * @return string
     */
    public static function makeSn(): string
    {
        $prefix = SerialNumber::generate(SerialNumber::TYPE_SHIPMENT_SN);
        //如果缓存不存在记录
        if (!Cache::get('SHIPMENT_SN')) {
            //从数据库查询最新的值
            $shipment = self::query()->latest('sn')->sharedLock()->select('sn')->get()->first();
            if ($shipment->sn ?? null) {
                //数据库存在，返回数据库的值加1
                Cache::increment('SHIPMENT_SN', (int) substr($shipment->sn, -4, 4) - 1000);
                return self::query()->latest('sn')->sharedLock()->select('sn')->get()->first()->sn + 1;
            }
            //数据库不存在，返回全新的值
            return $prefix . date('Ymd', time()) . (Cache::increment('SHIPMENT_SN', 1) + 1000);
        }
        //存在记录，直接加1
        return $prefix . date('Ymd', time()) . (Cache::increment('SHIPMENT_SN', 1) + 1000);
    }
}
