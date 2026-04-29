<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsDiscountRule extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_goods_discount_rules';

    protected $guarded = [];

    const DISCOUNT_TYPE_PERCENTAGE = 'percentage';
    const DISCOUNT_TYPE_FIXED_AMOUNT = 'fixed_amount';

    public function items()
    {
        return $this->hasMany(GoodsDiscountRuleItem::class, 'rule_id', 'id');
    }

    public function custom()
    {
        return $this->belongsTo(Custom::class, 'customer_id', 'id');
    }

    public function staff()
    {
        return $this->belongsTo(Admin::class, 'staff_id', 'id');
    }

    public static function init($params, $type = 'create')
    {
        $data = [
            'name' => $params['name'] ?? '',
            'customer_id' => $params['customer_id'],
            'discount_type' => $params['discount_type'],
            'discount_value' => $params['discount_value']
        ];
        if ($type == 'create') {
            $data['staff_id'] = getAdminId();
            $data['code'] = '';
        }
        return $data;
    }

}
