<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class PurchasePlan extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_purchase_plans';

    protected $guarded = [];

    const TYPE_BY_ORDER = 1;
    const TYPE_BY_CUSTOM = 2;

    public function items()
    {
        return $this->hasMany(PurchasePlanItem::class, 'plan_id', 'id');
    }

    public static function init($params, $type = 'create')
    {
        $data = [
            'type' => $params['type'] ?? self::TYPE_BY_CUSTOM,
            'create_user_id' => $params['create_user_id'] ?? 0,
            'remark' => $params['remark'] ?? '',
        ];
        if ($type == 'create') {
            $data['plan_sn'] = self::getPlanSn();
            $data['status'] = $params['status'] ?? 0;
        }
        return $data;
    }

    public function purchase()
    {
        return $this->belongsToMany(PurchaseOrdersModel::class, 'dsp_plan_purchase_relation', 'plan_id','purchase_id');
    }

    public function order()
    {
        return $this->belongsToMany(Order::class, 'dsp_plan_shop_order_relation', 'plan_id', 'order_id');
    }

    public function user()
    {
        return $this->belongsTo(Admin::class, 'create_user_id', 'id');
    }

    public static function getPlanSn(): string
    {
        $pre = 'PL';
        if (Cache::has('purchasePlanSn')) {
            $increment = Cache::increment('purchasePlanSn');
        } else {
            $latest = self::query()->latest('id')->first();
            if (empty($latest)) {
                $increment = now()->unix();
            } else {
                $increment = substr($latest->plan_sn, -10);
                $increment ++;
            }
            Cache::increment('purchasePlanSn', $increment);
        }
        return $pre. $increment;
    }

}
