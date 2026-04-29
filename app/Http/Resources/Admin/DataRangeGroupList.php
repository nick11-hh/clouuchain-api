<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class DataRangeGroupList extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'admins_count' => $this->admins_count ?? 0,
            'creator_name' => $this->creator->username,
            'created_at' => (string)$this->created_at,
            'range_type_permission' => DataRangeTypePermissionList::collection($this->whenLoaded('rangeTypePermissions')),
        ];
    }
}
