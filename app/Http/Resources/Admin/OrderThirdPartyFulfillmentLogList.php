<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderThirdPartyFulfillmentLogList extends JsonResource
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
            'id'            => $this->id,
            'order_id'      => $this->order_id,
            'order_info'    => $this->order_info,
            'platform'      => $this->platform,
            'platform_name' => $this->platform_name,
            'status'        => $this->status,
            'status_name'   => $this->status_name,
            'content'       => $this->content,
            'operate_id'    => $this->operate_id,
            'operate_name'  => $this->operateUser->username ?? '系统',
            'created_at'    => (string)$this->created_at,
        ];
    }
}
