<?php

namespace App\Models;

use App\Lib\Platform;
use App\Models\Traits\CustomerFilter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopModel extends Model
{
    use HasFactory;
    use SoftDeletes;
    use CustomerFilter;

    protected $table = 'dsp_shop';
    protected $fillable = [];
    protected $appends = [
        'status_name'
    ];

    protected $casts = [
        'ext_data' => 'array'
    ];

    const STATUS_AUTH = 1;//授权成功
    const STATUS_NOT_AUTH = 0;//未授权
    const STATUS_AUTH_CANCEL = 2;//取消授权
    const STATUS_AUTH_EXPIRE = 3;//授权失效

    const ENABLE = 1;
    const DISABLE = 0;

    const PLATFORM_ALL = '';

    public const AUTHORIZATION_TYPE_OAUTH = 1; //oauth授权
    public const AUTHORIZATION_TYPE_PRIVATE_APP = 2; //私有应用授权

    const PLATFORM_LIST = [
        Platform::SHOPIFY => 'Shopify',
        /*Platform::SALLA => 'Salla',
        Platform::ZID => 'Zid',*/
        Platform::TIKTOK => 'Tiktok',
        Platform::WOOCOMMERCE => 'Woocommerce',
        Platform::LOCAL => 'Local',
        self::PLATFORM_ALL => 'All',
    ];

    public static function statusList()
    {
        return [
            self::STATUS_NOT_AUTH => __('未授权'),
            self::STATUS_AUTH => __('授权成功'),
            self::STATUS_AUTH_CANCEL => __('取消授权'),
            self::STATUS_AUTH_EXPIRE => __('授权失效'),
        ];
    }

    public function order()
    {
        return $this->hasMany(Order::class, 'shop_id', 'id');
    }

    public function getStatusNameAttribute()
    {
        return self::statusList()[$this->status] ?? '-';
    }

    public function getPlatformNameAttribute(): string
    {
        return self::PLATFORM_LIST[$this->platform] ?? '-';
    }


    public function customer()
    {
        return $this->belongsTo(Custom::class, 'customer_id', 'id');
    }

    public function setting()
    {
        return $this->hasOne(ShopSetting::class, 'shop_id', 'id');
    }


}
