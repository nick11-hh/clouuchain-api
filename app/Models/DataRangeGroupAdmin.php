<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataRangeGroupAdmin extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_data_range_group_admins';

    protected $guarded = [];
}
