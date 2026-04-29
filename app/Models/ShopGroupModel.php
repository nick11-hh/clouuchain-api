<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopGroupModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_shop_groups';

    public function admin(): HasMany
    {
        return $this->hasMany(Admin::class, 'id', 'admin_id');
    }

    public function shops(): HasMany
    {
        return $this->hasMany(ShopGroupsMappingModel::class, 'shop_group_id', 'id');
    }

    public static function init($params): array
    {
        return [
            'admin_id'    => $params['admin_id'] ?? auth('admin')->id(),
            'group_name'  => $params['group_name'] ?? '',
            'description' => $params['description'] ?? '',
        ];
    }

}
