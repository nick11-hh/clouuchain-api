<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\LikeScope;

class OrderBox extends Model
{
    use Basis,
        LikeScope;

    protected $table = 'dsp_order_boxes';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    public function getVolumeAttribute()
    {
        if(!$this->length || !$this->width || !$this->height) return 0;
        return (float) sprintf('%.2f', $this->length * $this->width * $this->height / 1000000);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * 装箱包裹
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function packages()
    {
        return $this->belongsToMany(
            Package::class,
        'dsp_order_box_package',
            'order_box_id',
            'package_id'
        );
    }

    /**
     * 订单发货快递公司
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function express()
    {
        return $this->hasOne(CompanyExpress::class, 'code', 'logistics_company');
    }

    public function declareBox()
    {
        return $this->hasOne(DeclareOrderBox::class,'box_id','id');
    }

    public function systemBox()
    {
        return $this->belongsTo(SystemBox::class, 'system_box_id', 'id');
    }

    public function preDeclareItems()
    {
        return $this->hasMany(PreDeclareOrderItem::class, 'order_box_id', 'id');
    }

    /**
     * 自提点订单
     * 订单会在多个自提点之间转运
     * 所以是一对多的关系
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function stationBoxes()
    {
        return $this->hasMany(StationOrderBox::class, 'box_sn', 'sn');
    }

    /**
     * 最新的自提点订单
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function stationBox()
    {
        return $this->hasOne(StationOrderBox::class, 'box_sn', 'sn')
            ->where('status', '!=', 0)
            ->latest();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function shipment()
    {
        return $this->belongsTo(Shipment::class,'shipment_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function reserveBox()
    {
        return $this->belongsTo(ReserveBoxNo::class, 'reserve_box_id', 'id');
    }

    public function groupSubOrders()
    {
        return $this->belongsToMany(
            Order::class,
            'dsp_order_box_gb_sub_orders',
            'order_box_id',
            'gb_sub_order_id'
        );
    }
}
