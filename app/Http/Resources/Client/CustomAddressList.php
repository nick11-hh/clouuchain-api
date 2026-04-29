<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomAddressList extends JsonResource
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
            'id' => $this->id,
            'custom_id' => $this->custom_id,
            'custom_name' => $this->custom->custom_name ?? '',
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'country_id' => (int)$this->country_id,
            'country_name' => $this->country->en_name ?? '',
            'province' => $this->province ?? '',
            'city' => $this->city,
            'address_detail' => $this->address_detail,
            'phone_number' => $this->phone_number,
            'email' => $this->email ?? '',
            'post_code' => $this->post_code,
            'tax_id' => $this->tax_id ?? '',
            'country_code' => $this->country_code ?? '',
            'province_code' => $this->province_code ?? '',
            'is_default' => $this->is_default,
            'country' => $this->country ?? '',
            'address_type' => $this->address_type,
            'phone_area_code' => $this->phone_area_code ?? '',
        ];
    }
}
