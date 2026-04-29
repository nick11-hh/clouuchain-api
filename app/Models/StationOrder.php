<?php

/**
 * @Author: h9471
 * @Created: 2020/3/23 12:35
 */

namespace App\Models;

use App\Models\Traits\Basis;
use App\Models\Traits\LikeScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StationOrder extends Model
{
    use Basis, LikeScope;

    public const STATUS_WAIT_RECEIVED = 0;   //自提点未签收
    public const STATUS_RECEIVED = 1;   //自提点签收
    public const STATUS_SHELVED = 2;    //自提点上架
    public const STATUS_SHIPPED = 3;    //自提点出库
    public const STATUS_SIGNED = 4;     //自提点签收
    public const STATUS_TRANSPORTED = 5;     //自提点转运出库

    protected $table = 'dsp_station_orders';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [
        'sign_images' => 'array',
    ];

    /**
     * 自提点
     *
     * @return BelongsTo
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(SelfPickupStation::class, 'station_id', 'id');
    }

    /**
     * 订单
     *
     * @return BelongsTo
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function getSignImagesAttribute(): array
    {
        $images = $this->getArrayAttributeByKey('sign_images');

        return array_map(function ($image) {
            return config('app.url') . $image;
        }, $images);
    }
}
