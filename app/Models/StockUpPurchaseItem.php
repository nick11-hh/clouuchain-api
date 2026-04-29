<?php

namespace App\Models;

use App\Models\Traits\Basis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockUpPurchaseItem extends Model
{
    use Basis,SoftDeletes;

    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $table = 'dsp_stock_up_purchase_item';
}
