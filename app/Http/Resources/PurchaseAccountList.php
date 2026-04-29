<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseAccountList extends JsonResource
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
            'id'           => $this->id,
            'account_name' => $this->account_name,
            'platform'     => $this->platform,
            'name'         => $this->name,
            'state'        => $this->state,
            'enable'       => boolval($this->enable),
            'remark'       => $this->remark,
            'auth_time'    => (string)$this->auth_time,
            'created_at'   => (string)$this->created_at,
            'updated_at'   => (string)$this->updated_at,
        ];
    }
}
