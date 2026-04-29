<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExpressLineIconsModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_express_line_icons';

    public function getIconAttribute($value): string
    {
        $path = request()->path();

        if (stripos($path, 'api/admin') === 0) {
            return $value;
        }
        return config('app.url') . $value;
    }
}
