<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class LogisticsTrackInfoModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_logistics_track_info';

    protected $casts = [
        'order_tracking_details' => 'array'
    ];
}
