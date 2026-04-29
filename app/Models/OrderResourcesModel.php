<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderResourcesModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_order_resources';

    const STATUS_CLAIM          = 0; // 待认领
    const STATUS_QUOTATION      = 1; // 报价中
    const STATUS_WAIT_CONFIRMED = 2; // 待确认
    const STATUS_SUCCESS        = 3; // 报价成功
    const STATUS_FAIL           = 4; // 拒绝报价
    const STATUS_DRAFT          = 5; // 草稿

    protected $casts = [
        'imgs' => 'array',
        'country_ids' => 'array',
    ];

    public function getStatusNameAttribute()
    {
        $status = [
            self::STATUS_CLAIM => __('待报价'),
            self::STATUS_QUOTATION => __('报价中'),
            self::STATUS_WAIT_CONFIRMED => __('待确认'),
            self::STATUS_SUCCESS => __('报价成功'),
            self::STATUS_FAIL => __('拒绝报价'),
            self::STATUS_DRAFT => __('草稿'),
        ];

        return $status[$this->status];
    }

    public function customer()
    {
        return $this->belongsTo(Custom::class, 'customer_id', 'id');
    }

    public function procure()
    {
        return $this->belongsTo(Admin::class, 'purchaser', 'id');
    }

    public function product()
    {
        return $this->belongsTo(GoodsSku::class, 'product_id', 'id');
    }

    public function consult() {
        return $this->hasOne(ConsultModel::class, 'order_id', 'id');
    }
    public static function init($data, $customer_id): array
    {

        return [
            'customer_id'  => $customer_id,
            'imgs'         => $data['imgs'] ?? [],
            'product_name' => $data['product_name'],
            'url'          => $data['url'] ?? '',
            'target_price' => $data['target_price'] ?? 0,
            'price'        => $data['price'] ?? 0,
            'remark'       => $data['remark'] ?? '',
            'desc'         => $data['desc'] ?? '',
            'status'       => $data['status'] ?? 0,
            'country_ids'  => $data['country_ids'] ?? [],
        ];
    }
}
