<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class PlatformVirtualSkuList extends JsonResource
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
            'id'                  => $this->id,
            'platform'            => $this->platform,
            'platform_variant_id' => $this->platform_variant_id,
            'staff_id'            => $this->staff_id,
            'created_at'          => (string)$this->created_at,
        ];
    }
}
