<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class CTUUserMessageInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'is_read' => $this->is_read,
            'ctu_message' => $this->ctuMessage,
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at,
        ];
    }
}
