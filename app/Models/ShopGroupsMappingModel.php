<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopGroupsMappingModel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_shop_groups_mappings';

    protected $guarded = [];

    protected $casts = [];

    public function shopGroup(): BelongsTo
    {
        return $this->BelongsTo(ShopGroupModel::class, 'id', 'shop_group_id');
    }

    public static function init($params): array
    {
        return [
            'shop_group_id' => $params['shop_group_id'] ?? 0,
            'shop_id'       => $params['shop_id'] ?? 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ];
    }

}
