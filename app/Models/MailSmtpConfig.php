<?php

namespace App\Models;

use App\Models\Traits\Basis;

class MailSmtpConfig extends Model
{
    use Basis;

    protected $table = 'dsp_mail_smtp_config';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];


}
