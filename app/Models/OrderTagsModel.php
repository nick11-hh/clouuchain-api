<?php

namespace App\Models;

use App\Models\Traits\CustomHasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 订单标签
 */
class OrderTagsModel extends Model
{
    use SoftDeletes, HasFactory, CustomHasTranslations;

    public $translatable = ['name', 'description'];

    protected $table = 'dsp_shop_order_tags';

    protected $guarded = [];

    public function mappings()
    {
        return $this->hasMany(OrderTagMappingsModel::class, 'shop_order_tag_id', 'id');
    }

    public static function init($data): array
    {
        return [
            'name'        => $data['name'],
            'sort'        => $data['sort'] ?? 0,
            'color'       => $data['color'] ?? '',
            'font_color'  => $data['font_color'] ?? '',
            'description' => $data['description'] ?? '',
        ];
    }
}
