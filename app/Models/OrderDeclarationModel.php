<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderDeclarationModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_order_logistics_customs_declaration';

    protected $casts = [
        'attributes' => 'array',
    ];

    public function orderItem()
    {
        return $this->hasOne(OrderLineItem::class, 'id', 'order_item_id');
    }

}
