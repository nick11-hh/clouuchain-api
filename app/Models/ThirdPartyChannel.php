<?php

namespace App\Models;

use App\Models\Traits\Basis;

/**
 * 第三方渠道
 *
 * Class AboutUs
 * @package App\Models
 */
class ThirdPartyChannel extends Model
{
    use Basis;

    protected $table = 'dsp_third_party_channel';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];
}

