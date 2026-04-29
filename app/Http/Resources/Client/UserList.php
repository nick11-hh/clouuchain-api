<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class UserList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_area_code' => $this->phone_area_code ?? '',
            'status' => $this->status,
            'status_name' => $this->status_name ?? '',
            'group_id' => $this->group_id,
            'group_name' => $this->userGroup->group_name ?? '',
            'last_login_at' => (string)$this->last_login_at,
            'created_at' => (string)$this->created_at
        ];
    }
}
