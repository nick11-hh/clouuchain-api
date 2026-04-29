<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;

class ExpressLineIcon extends Model
{
    use Basis, HasValidateUnique;

    protected $table = 'dsp_express_line_icons';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    public function getIconAttribute($value)
    {
        $path = request()->path();

        if (stripos($path, 'api/admin') === 0) {
            return $value;
        }
        return config('app.url') . $value;
    }
}
