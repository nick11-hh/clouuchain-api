<?php

namespace App\Models\Views;

use App\Models\MemberLevel;
use App\Models\Traits\Basis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;


/**
 * 成长值-积累
 *
 * Class AboutUs
 * @package App\Models
 */
class VUserMember extends Model
{
    use Basis;

    protected $table = 'v_dsp_user_member';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    /**
     * 等级
     *
     * @return HasOne
     */
    public function level(): HasOne
    {
        return $this->hasOne(MemberLevel::class, 'id', 'level_id');
    }
}

