<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomGroup extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_custom_groups';

    protected $guarded = [];

    protected $casts = [
        'menu_limit' => 'array'
    ];

    public function customs()
    {
        return $this->hasMany(Custom::class, 'group_id', 'id');
    }

    static public function init($data)
    {
        return [
            'group_name' => $data['group_name'],
            'description' => $data['description'] ?? '',
            'menu_limit' => $data['menu_limit'] ?? [],
            'status' => $data['status'] ?? 1,
            'is_default' => $data['is_default'] ?? 0,
        ];
    }
}
