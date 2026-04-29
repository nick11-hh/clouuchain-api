<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThirdPartySystemConfigModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_third_party_system_configs';

    protected $guarded = [];

    protected $casts = [
        'extend' => 'array'
    ];

    public const PLATFORM_BREVO = 'brevo';

    public const PLATFORM_LIST = [
        self::PLATFORM_BREVO => 'Brevo',
    ];

    public const STATUS_ENABLE = 1;

    public function getPlatformNameAttribute()
    {
        return self::PLATFORM_LIST[$this->platform] ?? '-';
    }

    public function scopeEnable($query)
    {
        return $query->where('status', self::STATUS_ENABLE);
    }

    public static function getBrevoConfig()
    {
        return self::enable()->where('platform', self::PLATFORM_BREVO)->first();
    }

}
