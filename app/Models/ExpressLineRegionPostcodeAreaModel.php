<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExpressLineRegionPostcodeAreaModel extends Model
{
    use HasFactory;

    public const TYPE_RANGE = 1; // 邮编范围
    public const TYPE_FIXED = 2; // 固定邮编

    public const CANADA_COUNTRY_ID = 179; //加拿大国家id

    protected $table = 'dsp_express_line_region_postcode_area';
}
