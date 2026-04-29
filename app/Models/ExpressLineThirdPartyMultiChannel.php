<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasCompanyId;

/**
 * 路线多接口渠道
 *
 * Class AboutUs
 * @package App\Models
 */
class ExpressLineThirdPartyMultiChannel extends Model
{
    use Basis;

    protected $table = 'dsp_express_line_third_party_multi_channels';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    public function expressLine()
    {
        return $this->belongsTo(ExpressLineModel::class, 'express_line_id', 'id');
    }

    public function dockingCompany()
    {
        return $this->belongsTo(OrderDockingType::class, 'docking_type', 'type');
    }
}

