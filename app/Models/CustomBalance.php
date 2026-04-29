<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomBalance extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_custom_balance';

    protected $guarded = [];

    protected $casts = [];

    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id');
    }

}
