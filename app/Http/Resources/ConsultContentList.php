<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ConsultContentList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // info('内容信息', $this->toArray());
        logger($this->user);
        return [
            'id'         => $this->id,
            'nickName'   => !empty($this->user) ? $this->user['username'] : $this->admin['name'],
            'content'    => $this->content,
            'is_right'   => empty($this->user) ? 1 : 0,
            'status'     => $this->status,
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at,
        ];
    }
}
