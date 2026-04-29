<?php

/**
 * 拆包清点配置model类
 */
namespace App\Models;

use App\Models\Traits\Basis;

class InventoryItem extends Model
{
    use Basis;

    protected $table = 'dsp_inventory_items';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    protected $fillable = [];

    protected static function boot()
    {
        static::bootTraits();
    }
}
