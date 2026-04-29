<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class InventoryStock extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dsp_inventory_stocks';

    protected $guarded = [];

    const STATUS_WAIT = 0;
    const STATUS_PROCESS = 1;
    const STATUS_FINISH = 2;
    const STATUS_QUIT = 3;

    const TYPE_SKU = 1;
    const TYPE_LOCATION = 2;
    const TYPE_WHOLE = 3;

    const SHOW_STOCK_INVENTORY = 1;
    const HIDE_STOCK_INVENTORY = 2;

    public static function statusList()
    {
        return [
            self::STATUS_WAIT => __('待盘点'),
            self::STATUS_PROCESS => __('盘点中'),
            self::STATUS_FINISH => __('盘点完成'),
            self::STATUS_QUIT => __('已作废')
        ];
    }

    public static function typeList()
    {
        return [
            self::TYPE_WHOLE => __('整仓盘点'),
            self::TYPE_SKU => __('SKU盘点'),
            self::TYPE_LOCATION => __('仓位盘点')
        ];
    }

    public static function inventoryMethod()
    {
        return [
            self::SHOW_STOCK_INVENTORY => __('明盘'),
            self::HIDE_STOCK_INVENTORY => __('暗盘'),
        ];
    }

    public function items()
    {
        return $this->hasMany(InventoryStockItem::class, 'inventory_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(WarehouseAddress::class,  'warehouse_id', 'id');
    }

    public function getStatusNameAttribute()
    {
        return self::statusList()[$this->status] ?? '-';
    }

    public function getTypeNameAttribute()
    {
        return self::typeList()[$this->type] ?? '-';
    }

    public function getMethodNameAttribute()
    {
        return self::inventoryMethod()[$this->method] ?? '-';
    }

    public static function init($params)
    {
        $operator = Admin::query()->findOrFail($params['operator_id']);
        return [
            'warehouse_id' => $params['warehouse_id'],
            'order_sn' => self::getOrderSn(),
            'type' => $params['type'],
            'method' => $params['method'],
            'is_zero' => $params['is_zero'],
            'operator_id' => $params['operator_id'],
            'operator' => $operator->name,
            'remark' => $params['remark'] ?? '',
        ];
    }

    public static function getOrderSn()
    {
        if (Cache::has('InventoryStockOrderSnCache')) {
            $increment = Cache::increment('InventoryStockOrderSnCache');
        } else {
            $latest = self::query()->latest('id')->first();
            if (empty($latest)) {
                $increment = now()->unix();
            } else {
                $increment = substr($latest->order_sn, -10);
                $increment ++;
            }
            Cache::increment('InventoryStockOrderSnCache', $increment);
        }
        return 'IN' . $increment;
    }

}
