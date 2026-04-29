<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class AdminOperationLogList extends JsonResource
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
            'id'          => $this->id,
            'type'        => $this->type,
            'opt_type'    => $this->opt_type,
            'opt_type_name'=> $this->opt_type_name,
            'admin_id'    => $this->admin_id,
            'admin_name'  => $this->admin ? $this->admin->name : '',
            'custom_id'   => $this->custom_id,
            'description' => $this->description,
            'created_at'  => (string) $this->created_at,
        ];
    }
}
