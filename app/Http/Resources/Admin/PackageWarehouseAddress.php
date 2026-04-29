<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class PackageWarehouseAddress extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'warehouse_name' => $this->warehouse_name,
            'receiver_name' => $this->receiver_name,
            'timezone' => $this->timezone,
            'phone' => $this->phone,
            'postcode' => $this->postcode,
            'address' => $this->address,
        ];
    }
}
