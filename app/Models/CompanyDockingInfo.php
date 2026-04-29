<?php

namespace App\Models;

use App\Models\Traits\Basis;

/**
 * 对接信息
 *
 * Class CompanyDockingInfo
 * @package App\Models
 */
class CompanyDockingInfo extends Model
{
    use Basis;

    protected $table = 'dsp_company_docking_info';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'info' => 'array',
        'data' => 'array',
        'sender' => 'array',
    ];
}
