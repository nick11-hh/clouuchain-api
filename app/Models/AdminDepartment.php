<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdminDepartment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_admin_departments';

    protected $guarded = [];

    public function department()
    {
        $this->belongsTo(Department::class, 'department', 'id');
    }

}
