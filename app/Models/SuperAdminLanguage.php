<?php

namespace App\Models;

use App\Models\Traits\Basis;

class SuperAdminLanguage extends Model
{
    use Basis;

    protected $table = 'dsp_sa_languages';

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
}
