<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;

class CTUMessage extends Model
{
    use Basis,
        CustomHasTranslations;

    protected $table = 'dsp_ctu_messages';

    public $translatable = ['title', 'content'];

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [

    ];

    //类型1-全体客户2-客户组3-客户
    public const TYPE_ALL = 1;
    public const TYPE_USER_GROUP = 2;
    public const TYPE_USER = 3;

    public const STATUS_WAIT = 0;
    public const STATUS_FINISH = 1;

    public function getTypeNameAttribute()
    {
        return self::typeList()[$this->type] ?? '';
    }

    public function getStatusNameAttribute()
    {
        return self::statusList()[$this->status] ?? '';
    }

    public static function statusList()
    {
        return [
            self::STATUS_WAIT => __('未发布'),
            self::STATUS_FINISH => __('已发布')
        ];
    }

    public static function typeList()
    {
        return [
            self::TYPE_ALL => __('全体客户'),
            self::TYPE_USER_GROUP => __('客户组'),
            self::TYPE_USER => __('指定客户'),
        ];
    }

    public function userGroups()
    {
        return $this->belongsToMany(
            CustomGroup::class,
            'dsp_ctu_message_to_user_groups',
            'message_id',
            'user_group_id'
        );
    }

    public function users()
    {
        return $this->belongsToMany(
            Custom::class,
            'dsp_ctu_message_to_users',
            'message_id',
            'user_id'
        );
    }
}
