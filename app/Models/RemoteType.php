<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;

class RemoteType extends Model
{
    use Basis, HasValidateUnique;

    public const SOURCE_CUSTOM = 0;
    public const SOURCE_SYSTEM = 1;

    protected $table = 'dsp_remote_types';

    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    public function remoteDestination()
    {
        return $this->hasMany(RemoteDestination::class, 'remote_type_id', 'id');
    }

    public function getSourceNameAttribute()
    {
        return self::sourceList()[$this->source] ?? '';
    }


    public static function sourceList()
    {
        return [
            self::SOURCE_CUSTOM => __('自定义'),
            self::SOURCE_SYSTEM => __('系统内置')
        ];
    }

}
