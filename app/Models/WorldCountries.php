<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Khsing\World\World;

/**
 * Class WorldCountries
 * @package App\Models
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/9/26 15:02
 */
class WorldCountries extends Model
{
    use HasFactory;

    protected $table = 'world_countries';

    protected $appends = [];

    protected $casts = [];

    protected $hidden = [];

    /**
     * 关联国家信息
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/27 17:30
     */
    public function countryCn()
    {
        return $this->belongsTo(WorldCountriesLocale::class, 'id', 'country_id')
            ->where('locale', 'zh-cn');
    }
}
