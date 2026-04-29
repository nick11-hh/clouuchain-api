<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderShippingAddressInfo extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
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
            'name'          => $this->name,
            'country_code'  => $this->country_code,
            'province_code' => $this->province_code,
            'created_at'    => (string)$this->created_at,
            'updated_at'    => (string)$this->updated_at,
        ];
    }
}
