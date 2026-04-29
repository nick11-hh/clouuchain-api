<?php

namespace App\Models;

use App\Models\Traits\Basis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ThirdWithdrawLog extends Model
{
    use Basis;

    protected $table = 'dsp_third_withdraw_log';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    protected static function boot()
    {
        static::bootTraits();
    }
}
