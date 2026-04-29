<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_departments';

    protected $guarded = [];


    public function children()
    {
        return $this->childDepartment()->with(['children'=> function($query) {
            $query->orderBy('created_at', 'desc');
        }]);
    }

    public function childDepartment()
    {
        return $this->hasMany(Department::class, 'parent_id', 'id');
    }

    public function admins()
    {
        return $this->hasManyThrough(Admin::class, AdminDepartment::class, 'department_id', 'id', 'id', 'admin_id');
    }

    public static function init($params)
    {
        return [
            'name' => $params['name'],
            'parent_id' => $params['parent_id'] ?? 0,
            'level' => $params['level'],
            'description' => $params['description'] ?? '',
        ];
    }

}
