<?php


namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CTUMessageList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_name' => $this->type_name,
            'title' => $this->title,
            'content' => $this->content,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'read_count' => $this->read_count,
            'total_count' => $this->total_count,
            'operator_id' => $this->operator_id,
            'operator' => $this->operator,
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at,
        ];
    }
}
