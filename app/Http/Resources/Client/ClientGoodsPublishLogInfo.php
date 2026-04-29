<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Resources\Json\JsonResource;

class ClientGoodsPublishLogInfo extends JsonResource
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
            'goods_id' => $this->goods_id,
            'info' => $this->info,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'created_at' => (string)$this->created_at,
        ];
    }
}
