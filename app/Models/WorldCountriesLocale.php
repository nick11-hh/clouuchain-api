<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 世界国家分布
 * Class WorldCountriesLocale
 * @package App\Models
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/9/27 17:30
 */
class WorldCountriesLocale extends Model
{
    use HasFactory;

    protected $table = 'world_countries_locale';

    protected $appends = [];

    protected $casts = [];

    protected $hidden = [];


}
