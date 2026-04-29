<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserGroup extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_user_groups';

    protected $guarded = [];

    protected $casts = [
        'menu_limit' => 'array'
    ];

    public function users()
    {
         return $this->hasMany(User::class, 'group_id', 'id');
    }


    public function routeMenus(): BelongsToMany
    {
        return $this->belongsToMany(
            ClientMenu::class,
            'dsp_client_groups_route_menus',
            'user_group_id',
            'route_menu_id'
        );
    }

    static public function init($data)
    {
        return [
            'custom_id' => $data['custom_id'],
            'group_name' => $data['group_name'],
            'description' => $data['description'] ?? '',
            'menu_limit' => $data['menu_limit'] ?? [],
            'is_default' => $data['is_default'] ?? 0,
        ];
    }
}
