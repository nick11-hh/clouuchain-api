<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;


/**
 * 用户会员表
 *
 * Class AboutUs
 * @package App\Models
 */
class UserMember extends Model
{
    use Basis, HasValidateUnique;

    protected $table = 'dsp_user_member';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    /**
     * 用户
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 等级
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function level()
    {
        return $this->hasOne(MemberLevel::class, 'id', 'level_id');
    }
}

