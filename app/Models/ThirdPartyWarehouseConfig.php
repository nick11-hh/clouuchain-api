<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThirdPartyWarehouseConfig extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_third_party_warehouse_configs';

    protected $guarded = [];

    protected $casts = [
        'relation_warehouse_id' => 'array',
    ];

    CONST PLATFORM_MABANG = 'mabang';
    CONST PLATFORM_DIANXIAOMI = 'dianxiaomi';
    CONST PLATFORM_YUNLIANTIAO = 'yunliantiao';

    CONST WAREHOUSE_PLATFORM_LIST = [
        self::PLATFORM_YUNLIANTIAO => '云链条',
        self::PLATFORM_MABANG => '马帮ERP',
        self::PLATFORM_DIANXIAOMI => '店小秘',
    ];

    CONST STATUS_ENABLE = 1;

    const MARK_IN_DISTRIBUTION_AFTER_PUSH_ORDER_DISABLED = 0; //禁用推送订单后标记配货中
    const MARK_IN_DISTRIBUTION_AFTER_PUSH_ORDER_ENABLE = 1; //启用推送订单后标记配货中

    public function getPlatformNameAttribute()
    {
        return self::WAREHOUSE_PLATFORM_LIST[$this->platform] ?? '-';
    }

    public function scopeEnable($query)
    {
        return $query->where('status', self::STATUS_ENABLE);
    }

    public static function getConfig()
    {
        return self::enable()->first();
    }

}
