<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


/**
 * Class UserTag
 */
class UserTag extends Model
{
    use Basis,
        HasValidateUnique;

    protected $table = 'dsp_user_tags';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'dsp_user_to_tags',
            'tag_id',
            'user_id'
        );
    }

    /**
     * @return BelongsToMany
     */
    public function salePrices(): BelongsToMany
    {
        return $this->belongsToMany(
            SalePrice::class,
            'dsp_sale_prices_user_tags',
            'user_tag_id',
            'sale_price_id'
        );
    }
}
