<?php

namespace App\Models;

use App\Models\Traits\Basis;

class AutomaticSignIn extends Model
{
    use Basis;

    protected $table = 'dsp_automatic_sign_in';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    protected $fillable = [
        'enabled', 'trigger_days', 'is_evaluate', 'evaluate_score', 'evaluate_content', 'company_id', 'created_at', 'updated_at'
    ];
}
