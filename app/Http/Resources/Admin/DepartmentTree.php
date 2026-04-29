<?php

namespace App\Http\Resources\Admin;

use App\Lib\Language;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentTree extends JsonResource
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
            'parent_id' => $this->parent_id,
            'name' => $this->name_translate ?: $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'status_name' => $this->status_name ?? '',
            'children' => self::collection($this->whenLoaded('children')),
            'level' => $this->level,
            'created_at' => (string)$this->created_at,
        ];
    }
}
