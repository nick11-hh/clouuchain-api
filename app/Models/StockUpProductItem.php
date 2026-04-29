<?php

namespace App\Models;

use App\Models\Traits\Basis;
use Illuminate\Database\Eloquent\Model;

class StockUpProductItem extends Model
{
    use Basis;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $table = 'dsp_stock_up_product_item';
}
