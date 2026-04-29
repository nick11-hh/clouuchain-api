<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LogisticsCustomsList extends JsonResource
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
            'id'         => $this->id,
            'name'       => $this->name,
            'cn_name'    => $this->cn_name,
            'en_name'    => $this->en_name,
            'unit_price' => $this->unit_price,
            'code'       => $this->code,
            'weight'     => $this->weight,
            'material'   => $this->material,
            'use_to'     => $this->use_to,
            'attributes' => $this->attributes,
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at,
        ];
    }
}
