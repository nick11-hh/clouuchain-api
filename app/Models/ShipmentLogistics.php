<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShipmentLogistics extends Model
{
    use Basis,
        CustomHasTranslations;

    protected $table = 'dsp_shipment_logistics';

    public $translatable = ['context'];

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

    /**
     * @return HasMany
     */
    public function orderLogistics(): HasMany
    {
        return $this->hasMany(OrderLogistics::class, 'shipment_logistics_id', 'id');
    }
}
