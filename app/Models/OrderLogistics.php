<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;

class OrderLogistics extends Model
{
    use Basis,
        CustomHasTranslations;

    public $translatable = ['context'];

    protected $table = 'dsp_order_logistics';

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
    protected $casts = [
        'country_site' => 'array'
    ];
}
