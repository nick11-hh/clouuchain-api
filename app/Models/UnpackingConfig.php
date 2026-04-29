<?php

/**
 * 拆包清点配置model类
 */
namespace App\Models;

use App\Models\Traits\Basis;

class UnpackingConfig extends Model
{
    use Basis;

    protected $table = 'dsp_unpacking_and_inventory_configuration';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'inventory_items' => 'array',
        'category' => 'array',
        'require_fields' => 'array'
    ];

    protected $appends = [];

    protected $fillable = [];
}
