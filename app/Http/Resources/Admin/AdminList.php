<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminList extends JsonResource
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
            'id'         => $this->id,
            'name'       => $this->name,
            'username'   => $this->username,
            'phone'      => $this->phone,
            'phone_area_code' => $this->phone_area_code ?? '',
            'email'      => $this->email,
            'enable'     => $this->enable,
            'group_id'   => $this->group_id,
            'group'      => $this->group,
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at,
            'check_auth' => $this->check_auth ?? 0,
            'admin_department' => $this->whenLoaded('adminDepartment'),
            'data_range_group_id' => $this->dataRangeGroup->data_range_group_id ?? '',
        ];
    }
}
