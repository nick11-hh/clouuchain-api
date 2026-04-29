<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Osiset\ShopifyApp\Contracts\ShopModel;

class ShopifyUser extends Authenticatable implements JWTSubject
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_shopify_users';

    protected $guarded = [];

    const STATUS_ENABLE = 1;
    const STATUS_DISABLE = 0;

    static public function init($params)
    {
        return [
            'username' => $params['username'],
            'email' => $params['email'] ?? '',
            'phone' => $params['phone'] ?? '',
            'password' => bcrypt($params['password']),
            'status' => $params['status'] ?? self::STATUS_ENABLE,
            'custom_id' => $params['custom_id'],
            'group_id' => $params['group_id'] ?? 0,
            'name' => $params['name'] ?? '',
            'is_main' => $params['is_main'] ?? 0
        ];
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'role' => 'client',
        ];
    }

    public function custom()
    {
        return $this->belongsTo(Custom::class, 'custom_id', 'id');
    }

    public function userGroup()
    {
        return $this->belongsTo(UserGroup::class, 'group_id', 'id');
    }

    public function getStatusNameAttribute()
    {
        return $this->statusList()[$this->status];
    }

    /** 用户状态
     * @return array
     */
    public static function statusList()
    {
       return [
           self::STATUS_DISABLE => __('禁用'),
           self::STATUS_ENABLE => __('启用'),
       ];
    }
}
