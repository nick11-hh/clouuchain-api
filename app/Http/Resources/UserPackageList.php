<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserPackageList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id ?? '',
            'user_name' => $this->owner->name ?? '',
            'status' => $this->status,
            'status_name' => $this->statusName,
            'sub_status' => $this->sub_status,
            'express_num' => $this->express_num,
            'express_company' => ExpressCompanyList::make($this->express),
            'package_name' => $this->package_name ?? '',
            'package_value' => ($this->package_value ?? '') === '' ? '' : $this->package_value / 100,
            'props' => PackagePropList::collection($this->prop),
            'package_weight' => $this->package_weight / 1000,
            'destination_country' => CountryWithoutAreaList::make($this->country),
            'length' => $this->length / 100,
            'width' => $this->width / 100,
            'height' => $this->height / 100,
            'created_at' => (string) $this->created_at,
            'in_storage_at' => (string) $this->in_storage_at ?? '',
        ];
    }
}
