<?php

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\HasValidateUnique;

class RemoteDestination extends Model
{
    use Basis, HasValidateUnique;

    protected $table = 'dsp_remote_destinations';

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

    public function getSourceNameAttribute()
    {
        return RemoteType::sourceList()[$this->source] ?? '';
    }

    public function remoteType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RemoteType::class, 'remote_type_id', 'id');
    }

    public function country(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }



}
