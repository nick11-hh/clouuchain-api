<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrdersModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_purchase_orders';

    const STATUS_DRAFT        = 0; // 草稿
    const STATUS_PENDING      = 1; // 待下单
    const STATUS_PURCHASED    = 2; // 已下单
    const STATUS_WAIT_STORAGE = 3; // 待入库
    const STATUS_IN_STOCK     = 4; // 已完成
    const STATUS_CANCELLED    = 5; // 有异常
    const STATUS_CANCEL       = 6; // 已取消


    public function skus()
    {
        return $this->hasMany(PurchaseOrdersItemsModel::class, 'purchase_order_id', 'id');
    }

    public function shopOrder()
    {
        return $this->belongsTo(Order::class, 'shop_order_id', 'id');
    }

    public function shop()
    {
        return $this->belongsTo(ShopModel::class, 'shop_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(WarehouseAddress::class, 'warehouse_id', 'id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'provider_id', 'id');
    }

    public function purchasePlan()
    {
        return $this->belongsToMany(PurchasePlan::class, 'dsp_plan_purchase_relation', 'purchase_id', 'plan_id');
    }

    public static function statusList():array
    {
        return [
            self::STATUS_DRAFT        => __('草稿'),
            self::STATUS_PENDING      => __('待下单'),
            self::STATUS_PURCHASED    => __('已下单'),
            self::STATUS_WAIT_STORAGE => __('待入库'),
            self::STATUS_IN_STOCK     => __('已完成'),
            self::STATUS_CANCELLED    => __('有异常'),
            self::STATUS_CANCEL       => __('已取消'),
        ];
    }

    public static function getStatusName(int $status)
    {
        return self::statusList()[$status] ?? '';
    }

    public function getStatusNameAttribute()
    {
        $status = self::statusList();

        return $status[$this->status];
    }

    public static function init($data, $isEdit = false)
    {
        $reData = [
            'shop_order_id'       => $data['shop_order_id'] ?? 0,
            'shop_id'             => $data['shop_id'] ?? 0,
            'platform'            => $data['platform'] ?? 1688,
            'platform_sn'         => $data['platform_sn'] ?? '',
            'shipment_number'     => $data['shipment_number'] ?? '',
            'warehouse_id'        => $data['warehouse_id'] ?? 0,
            'provider_id'         => $data['supplier_id'] ?? 0,
            'purchase_user_id'    => $data['purchase_user_id'] ?? 0,
            'remark'              => $data['remark'] ?? '',
            'transaction_method'  => $data['transaction_method'] ?? 0,
            'purchase_account_id' => $data['purchase_account_id'] ?? 0,
            'expect_time'         => $data['expect_time'] ?? null,
            'other_fees'          => $data['other_fees'] ?? 0,
            'freight'             => $data['freight'] ?? 0,
            'message'             => $data['message'] ?? '',
        ];

        if(!$isEdit) {
            $reData['status'] = $data['status'] ?? 0;
            $reData['order_sn'] = self::generateSn();
        }

        return $reData;
    }

    // public static function init($data)
    // {
    //     return [
    //         'order_sn'         => self::generateSn(),
    //         'warehouse_id'     => $data['warehouse_id'] ?? 0,
    //         'provider_id'      => $data['supplier_id'] ?? 0,
    //         'shop_order_id'    => $data['shop_order_id'] ?? 0,
    //         'shop_id'          => $data['shop_id'] ?? 0,
    //         'platform'         => $data['platform'] ?? 1,
    //         'platform_sn'      => $data['platform_sn'] ?? '',
    //         'shipment_number'  => $data['shipment_number'] ?? '',
    //         'status'           => $data['status'] ?? 0,
    //         'purchase_user_id' => $data['purchase_user_id'] ?? 0,
    //         'remark'           => $data['remark'] ?? '',
    //     ];
    // }


    public static function generateSn()
    {
        $no = 'PO'.date('Ymd');
        $order_sn = self::where('order_sn', 'like', $no.'%')->orderBy('id', 'desc')->value('order_sn');

        if($order_sn) return self::incrementNumberSuffix($order_sn);

        return $no.'0001';
    }

    public static function incrementNumberSuffix($string) {
        // 匹配字符串中的数字部分
        preg_match('/(\d+)$/', $string, $matches);

        // 如果找到数字，增加1并替换原来的数字
        if (!empty($matches)) {
            $number = intval($matches[0]) + 1;
            // 将数字填充到和原数字相同的位数
            $new_number = sprintf("%0" . strlen($matches[0]) . "d", $number);
            // 将新数字替换原来的数字
            return preg_replace('/\d+$/', $new_number, $string);
        } else {
            // 如果没有找到数字，直接在末尾加1
            return $string . '1';
        }
    }
}
