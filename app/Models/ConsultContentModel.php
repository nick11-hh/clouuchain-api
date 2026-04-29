<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ConsultContentModel extends Model
{
    use HasFactory;
    protected $table = 'dsp_consult_content';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'customer_service_id', 'id');
    }
}
