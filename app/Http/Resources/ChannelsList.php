<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChannelsList extends JsonResource
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
            'id'                  => $this->id,
            'code'                => $this->code,
            'name'                => $this->name,
            'enable'              => $this->enable,
            'spec'                => $this->spec,
            'is_print_order_info' => $this->is_print_order_info,
            'tail_course'         => $this->tail_course,
            'created_at'          => (string)$this->created_at,
            'updated_at'          => (string)$this->updated_at,
        ];
    }
}
