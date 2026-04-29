<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExpressLineLabelsModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_express_line_labels';

    protected $appends = [
        'label_name',
    ];

    public function getLabelNameAttribute()
    {
        return $this->name;
    }
}
