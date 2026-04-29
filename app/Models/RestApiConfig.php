<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RestApiConfig extends Model
{
    use SoftDeletes;

    public const TYPE_1 = 1; //仿Woocommerce REST API
 
    public const PERMISSION_1 = 1; // 读
    public const PERMISSION_2 = 2; // 写
    public const PERMISSION_3 = 3; // 读写

    protected $table = 'dsp_rest_api_config';

    protected $guarded = [];

    public static function getTypeList()
    {
        return [
            self::TYPE_1 => __('仿Woocommerce REST API'),
        ];
    }

    public static function getPermissionList()
    {
        return [
            self::PERMISSION_1 => __('读'),
            self::PERMISSION_2 => __('写'),
            self::PERMISSION_3 => __('读/写')
        ];
    }

    public function getTypeNameAttribute()
    {
        return $this->getTypeList()[$this->type] ?? '-';
    }

    public function getPermissionNameAttribute()
    {
        return $this->getPermissionList()[$this->permission] ?? '-';
    }
}
