<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ConsultModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_consult';

    public function contents()
    {
        return $this->hasMany(ConsultContentModel::class, 'consult_id', 'id');
    }
}
