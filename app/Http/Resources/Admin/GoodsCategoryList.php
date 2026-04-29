<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class GoodsCategoryList extends JsonResource
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
            'parent_name' => $this->parent->name ?? '',
            'name' => $this->name,
            'description' => $this->description,
            'image' => $this->image,
            'status' => $this->status,
            'status_name' => $this->status_name ?? '',
            'sort' => $this->sort ?? '',
            'operator' => $this->operator ?? 0,
            'operator_name' => $this->admin->name ?? '',
            'created_at' => (string)$this->created_at,
            'is_recommend' => (string)$this->recommended_time ? 1 : 0,
        ];
    }
}
