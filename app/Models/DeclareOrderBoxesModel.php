<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeclareOrderBoxesModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_declare_order_boxes';

    public function items()
    {
        return $this->belongsToMany(
            DeclareOrderItemsModel::class,
            'dsp_declare_order_box_items',
            'declare_box_id',
            'declare_item_id'
        );
    }

    public function declareOrder()
    {
        return $this->belongsTo(DeclareOrderModel::class, 'declare_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function box()
    {
        return $this->belongsTo(OrderBoxesModel::class, 'box_id', 'id');
    }
}
