<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchasePlanItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_purchase_plan_items';

    protected $guarded = [];

    protected $casts = [
        'images' => 'array',
    ];

    const STATUS_NORMAL = 0;
    const STATUS_CANCEL = 1;

    public static function init($params, $type = 'create')
    {
        $data = [
            'images' => $params['images'],
            'goods_name' => $params['goods_name'],
            'spec_name' => $params['spec_name'],
            'plan_qty' => $params['plan_qty'],
            'goods_sku_id' => $params['goods_sku_id'],
            'order_sn' => $params['order_sn'] ?? '',
            'order_item_id' => $params['order_item_id'] ?? 0,
            'plan_procurement_time' => $params['plan_procurement_time'] ?? null,
            'supplier_id' => $params['supplier_id'] ?? 0,
            'shop_id' => $params['shop_id'] ?? 0,
            'warehouse_id' => $params['warehouse_id'] ?? 0,
        ];
        if ($type == 'create') {
            $data['sn'] = $params['item_sn'];
            $data['plan_id'] = $params['plan_id'];
        }
        return $data;
    }

    public function plan()
    {
        return $this->belongsTo(PurchasePlan::class, 'plan_id', 'id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    public function shop()
    {
        return $this->belongsTo(ShopModel::class, 'shop_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(WarehouseAddress::class, 'warehouse_id', 'id');
    }

    public function goodsSku()
    {
        return $this->belongsTo(GoodsSku::class, 'goods_sku_id', 'id');
    }
}
