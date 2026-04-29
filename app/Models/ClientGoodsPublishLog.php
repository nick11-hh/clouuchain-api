<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientGoodsPublishLog extends Model
{
    use HasFactory;

    protected $table = 'dsp_client_goods_publish_log';

    protected $guarded = [];

    protected $casts = [
        'ext' => 'array'
    ];

    const PUBLISH_SUCCESS = 1;
    const PUBLISH_ERROR = 2;

    public static function statusList()
    {
        return [
            self::PUBLISH_SUCCESS => __('刊登成功'),
            self::PUBLISH_ERROR => __('刊登失败'),
        ];
    }

    public function getStatusNameAttribute()
    {
        return self::statusList()[$this->status];
    }

}
