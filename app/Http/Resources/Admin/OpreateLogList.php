<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class OpreateLogList extends JsonResource
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
            'admin_id'      => $this->admin_id,
            'admin_name'    => $this->admin->name ?? '',
            'content'       => $this->content ?? '',
            'created_at'    => (string) $this->created_at,
        ];
    }
}
