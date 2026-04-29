<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class UserGroupList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'group_name' => $this->group_name,
            'description' => $this->description,
            'menu_limit' => $this->menu_limit,
            'is_default' => $this->is_default,
            'user_count' => $this->user_count ?? 0,
            'created_at' => (string)$this->created_at
        ];
    }
}
