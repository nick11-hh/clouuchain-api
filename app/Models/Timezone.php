<?php

namespace App\Models;

use App\Models\Traits\Basis;

class Timezone extends Model
{
    use Basis;

    protected $table = 'dsp_timezone';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];
}
