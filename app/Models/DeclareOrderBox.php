<?php

namespace App\Models;

use App\Models\Traits\Basis;

/**
 * 申报订单箱子明细
 */
class DeclareOrderBox extends Model
{
    use Basis;

    protected $table = 'dsp_declare_order_boxes';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'address' => 'array',
    ];


    public function items()
    {
        return $this->belongsToMany(
            DeclareOrderItem::class,
            'dsp_declare_order_box_items',
            'declare_box_id',
            'declare_item_id'
        );
    }

    public function declareOrder()
    {
        return $this->belongsTo(DeclareOrder::class, 'declare_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function box()
    {
        return $this->belongsTo(OrderBox::class, 'box_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function dockingConfig()
    {
        return $this->belongsTo(DockingConfig::class, 'docking_type', 'id');
    }
}
