<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderShippingAddressInfo extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'first_name'    => $this->first_name,
            'last_name'     => $this->last_name,
            'address1'      => $this->address1,
            'address2'      => $this->address2,
            'phone'         => $this->phone,
            'city'          => $this->city,
            'zip'           => $this->zip,
            'province'      => $this->province,
            'country'       => $this->country,
            'company'       => $this->company,
            'name'          => $this->name,
            'country_code'  => $this->country_code,
            'province_code' => $this->province_code,
            'tax'           => $this->tax,
            'created_at'    => (string)$this->created_at,
            'updated_at'    => (string)$this->updated_at,
        ];
    }
}
