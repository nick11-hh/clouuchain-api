<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformSkuMapping extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_platform_sku_mappings';

    protected $guarded = [];

    protected $casts = [];

}
