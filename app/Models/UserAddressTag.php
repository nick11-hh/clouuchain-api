<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


/**
 * Class UserTag
 */
class UserAddressTag extends Model
{
    use Basis;

    protected $table = 'dsp_user_address_tags';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    /**
     * @return BelongsToMany
     */
    public function addresses(): BelongsToMany
    {
        return $this->belongsToMany(
            UserAddress::class,
            'dsp_user_addresses_tags',
            'tag_id',
            'address_id'
        );
    }

    /**
     * @return BelongsToMany
     */
    public function conditions(): BelongsToMany
    {
        return $this->belongsToMany(
            ExpressLineRuleCondition::class,
            'dsp_user_address_tags_conditions',
            'address_id',
            'tag_id'
        );
    }
}
