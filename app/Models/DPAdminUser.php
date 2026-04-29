<?php

namespace App\Models;

use App\Models\Traits\Basis;

class DPAdminUser extends Model
{
    use Basis;

    protected $table = 'dsp_data_permission_admin_users';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];


    public static function batchInit(int $dpId, int $adminId, string $fieldId, array $values)
    {
        return collect($values)->map(function ($value) use ($dpId, $adminId, $fieldId) {
            return [
                'dp_id' => $dpId,
                'admin_id' => $adminId,
                'field_id' => $fieldId,
                'id_value' => $value,
                'created_at' => now(),
                'updated_at' => now()
            ];
        })->toArray();
    }

    public function dp()
    {
        return $this->belongsTo(DataPermission::class, 'dp_id', 'id');
    }

    public function dpAdminUserAble()
    {
        return $this->morphTo(__FUNCTION__, 'field_id', 'id_value');
    }

}
