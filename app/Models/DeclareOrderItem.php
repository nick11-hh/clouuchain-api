<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use App\Models\Traits\HasValidateUnique;

/**
 * 申报订单明细
 */
class DeclareOrderItem extends Model
{
    use Basis;

    protected $table = 'dsp_declare_order_items';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    public function declareOrder()
    {
        return $this->belongsTo(DeclareOrder::class, 'declare_id', 'id');
    }

    public function box()
    {
        return $this->belongsToMany(
            DeclareOrderBox::class,
            'dsp_declare_order_box_items',
            'declare_item_id',
            'declare_box_id'
        )->first();
    }


    public function getUnitNameAttribute()
    {
        return !empty($this->unit) ? DeclareOrder::unitList()[$this->unit] ?? '' : '';
    }

    public function getCurrencyNameAttribute()
    {
        return !empty($this->currency) ? DeclareOrder::currencyList()[$this->currency] ?? '' : '';

    }

}
