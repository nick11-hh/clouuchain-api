<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class RestApiConfigListResource extends JsonResource
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
            'host' => 'https://' . request()->getHost(),
            'type' => $this->type,
            'type_name' => $this->type_name,
            'description' => $this->description,
            'key_cover' => mask_string($this->key),
            'key' => $this->key,
            'secret' => $this->secret,
            'permission' => $this->permission,
            'permission_name' => $this->permission_name,
            'admin_id' => $this->admin_id,
            'created_at' => (string)$this->created_at,
        ];
    }
}
