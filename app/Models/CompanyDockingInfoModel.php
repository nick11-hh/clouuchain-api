<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyDockingInfoModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_company_docking_info';

    protected $casts = [
        'info' => 'array',
        'data' => 'array',
        'sender' => 'array',
    ];
}
