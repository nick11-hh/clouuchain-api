<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class ThirdPartyWarehouseConfigInfo extends JsonResource
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
            'platform'      => $this->platform,
            'platform_name' => $this->platform_name,
            'app_key'       => $this->app_key,
            'status'        => $this->status,
        ];
    }
}
