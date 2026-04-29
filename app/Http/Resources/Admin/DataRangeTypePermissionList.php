<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class DataRangeTypePermissionList extends JsonResource
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
            'data_type' => $this->data_type,
            'data_type_name' => $this->data_type_name,
            'range_type' => $this->range_type,
            'range_type_name' => $this->range_type_name,
            'range_value' => $this->range_value,
        ];
    }
}
