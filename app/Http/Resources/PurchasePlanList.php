<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PurchasePlanList extends JsonResource
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
            'id'             => $this->id,
            'plan_sn'        => $this->plan_sn,
            'type'           => $this->type,
            'status'         => $this->status,
            'create_user_id' => $this->create_user_id,
            'remark'         => $this->remark,
            'user'           => $this->user,
            'items'          => PurchasePlanItemList::collection($this->items),
            'order'          => $this->order,
            'order_ids'      => empty($this->order_ids) ? '-' : $this->order_ids,
            'created_at'     => (string)$this->created_at,
            'updated_at'     => (string)$this->updated_at,
        ];
    }
}
