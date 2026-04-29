<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataRangeGroup extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_data_range_groups';

    protected $guarded = [];

    public function dataRangeGroupAdmins()
    {
        return $this->hasmany(DataRangeGroupAdmin::class, 'data_range_group_id', 'id');
    }
    public function admins()
    {
        return $this->hasManyThrough(Admin::class, DataRangeGroupAdmin::class, 'data_range_group_id', 'id', 'id', 'admin_id');
    }

    public function rangeTypePermissions()
    {
        return $this->hasMany(DataRangeTypePermission::class, 'data_range_group_id', 'id');
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'creator_id', 'id');
    }

    public static function init($params, $create = 0)
    {
        $data = [
            'name' => $params['name'],
            'description' => $params['description'],
        ];
        if ($create) {
            $data['creator_id'] = getAdminId();
        }
        return $data;
    }
}
