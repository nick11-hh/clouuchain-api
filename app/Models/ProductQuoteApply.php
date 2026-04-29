<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductQuoteApply extends Model
{
    use HasFactory;

    protected $table = 'dsp_product_quote_applies';

    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(ProductQuoteApplyItem::class, 'apply_id', 'id');
    }

}
