<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlatformVirtualSku extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_platform_virtual_skus';

    protected $guarded = [];

}
