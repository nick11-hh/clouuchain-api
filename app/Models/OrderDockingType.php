<?php

namespace App\Models;

use App\Models\Traits\Basis;

class OrderDockingType extends Model
{
    use Basis;

    protected $table = 'dsp_order_docking_types';

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
    ];
}
