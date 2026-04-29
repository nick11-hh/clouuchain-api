<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopPlatformConfig extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_shop_platform_configs';

    protected $guarded = [];


    public static function getPlatformApplicationName($platform)
    {
        $shopifyConfig = ShopPlatformConfig::query()->where('platform', $platform)->first();
        return empty($shopifyConfig->application_name) ?  'Yunliantiao dropshipping' : $shopifyConfig->application_name;
    }
}
