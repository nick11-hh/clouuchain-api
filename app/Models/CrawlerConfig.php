<?php

namespace App\Models;

use App\Models\Traits\Basis;

class CrawlerConfig extends Model
{
    use Basis;

    protected $table = 'dsp_crawler_configs';

    protected $guarded = [];

    protected $hidden = [
        'password'
    ];

    protected $appends = [];

    public function company()
    {
        return $this->belongsTo(Admin::class, 'company_id', 'id');
    }

    public function express()
    {
        return $this->belongsTo(ExpressCompany::class, 'express_company_id', 'id');
    }
}
