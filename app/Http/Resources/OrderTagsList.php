<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderTagsList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'sort'        => $this->sort,
            'color'       => $this->color,
            'font_color'  => $this->font_color,
            'description' => $this->description,
            'created_at'  => (string)$this->created_at,
        ];
    }
}
