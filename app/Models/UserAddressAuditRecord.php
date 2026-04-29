<?php

namespace App\Models;

use App\Models\Traits\Basis;

class UserAddressAuditRecord extends Model
{
    use Basis;

    protected $table = 'dsp_user_address_audit_records';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];
}
