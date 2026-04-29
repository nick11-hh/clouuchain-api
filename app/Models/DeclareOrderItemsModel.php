<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeclareOrderItemsModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_declare_order_items';

    public function declareOrder()
    {
        return $this->belongsTo(DeclareOrderModel::class, 'declare_id', 'id');
    }

    public function box()
    {
        return $this->belongsToMany(
            DeclareOrderBoxesModel::class,
            'dsp_declare_order_box_items',
            'declare_item_id',
            'declare_box_id'
        )->first();
    }


    public function getUnitNameAttribute()
    {
        return !empty($this->unit) ? DeclareOrderModel::unitList()[$this->unit] ?? '' : '';
    }

    public function getCurrencyNameAttribute()
    {
        return !empty($this->currency) ? DeclareOrderModel::currencyList()[$this->currency] ?? '' : '';

    }
}
