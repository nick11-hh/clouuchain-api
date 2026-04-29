<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;

class Localization extends Model
{
    use Basis, HasValidateUnique;

    protected $table = 'dsp_localization';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];
}
