<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserOrderList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->user->name,
            'status' => $this->status,
            'status_name' => $this->statusName,
            'order_sn' => $this->order_sn ?? '',
            'express_line' => [
                'id' => $this->express_line_id,
                'name' => $this->expressLine->name,
            ],
            'address' => $this->address,
            'length' => $this->length / 100,
            'width' => $this->width / 100,
            'height' => $this->height / 100,
            'except_weight' => (float) sprintf('%.3f', $this->exceptWeight),
            'actual_weight' => (float) sprintf('%.3f', $this->actualWeights),
            'except_package_weight' => (float) sprintf('%.3f', $this->packages->sum->package_weight / 1000),
            'except_package_volume_weight' => (float) sprintf('%.3f', $this->packages->sum->package_volume_weight / 1000),
            'volume_weight' => $this->volume_weight / 1000,
            'payment_weight' => $this->payment_weight / 1000,
            'pack_weight' => $this->pack_weight / 1000,
            'payment_fee' => $this->payment_fee / 100 ?? '',
            'actual_payment_fee' => $this->actual_payment_fee / 100 ?? '',
            'created_at' => (string) $this->created_at,
        ];
    }
}
