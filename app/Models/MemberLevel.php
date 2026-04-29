<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use App\Models\Traits\HasValidateUnique;
use App\Models\Views\VUserMember;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;


/**
 * 会员等级表
 *
 * Class AboutUs
 * @package App\Models
 */
class MemberLevel extends Model
{
    use Basis, HasValidateUnique, CustomHasTranslations;

    protected $table = 'dsp_member_level';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    public $translatable = [ 'name'];

    /**
     * @return BelongsToMany
     */
    public function salePrices(): BelongsToMany
    {
        return $this->belongsToMany(
            SalePrice::class,
            'dsp_sale_prices_user_levels',
            'user_level_id',
            'sale_price_id'
        );
    }

    /**
     * @return HasMany
     */
    public function userMembers(): HasMany
    {
        return $this->hasMany(VUserMember::class, 'level_id', 'id');
    }
}

