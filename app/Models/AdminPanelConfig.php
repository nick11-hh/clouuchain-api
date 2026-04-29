<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;

class AdminPanelConfig extends Model
{
    use Basis, CustomHasTranslations;

    public $translatable = ['title', 'login_title', 'login_logo', 'login_image', 'sidebar_title', 'sidebar_image'];

    protected $table = 'dsp_admin_panel_config';

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
