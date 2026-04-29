<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailTemplateList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->getContent($this->content),
            'type' => $this->type,
            'type_name' => $this->type_name,
            'enabled' => $this->enabled == '1' ? true : false,
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at,
        ];
    }

    public function getContent($content)
    {
        $data = $content ? json_decode($content, true) : [];
        return $data['default'] ?? '';
    }
}
