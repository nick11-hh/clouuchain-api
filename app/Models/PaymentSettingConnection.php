<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentSettingConnection extends Model
{
    use Basis,HasFactory,CustomHasTranslations;
    //用于翻译
    public $translatable = ['name', 'content'];

    protected $table = 'dsp_payment_settings_connection';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];
}
