<?php


namespace App\Http\Resources\Admin;


use Illuminate\Http\Resources\Json\JsonResource;

class PromotionList extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'custom_name'       => $this->custom_name,
            'custom_phone'      => $this->custom_phone,
            'custom_email'      => $this->custom_email,
            'commission_rate'   => $this->commission_rate,
            'status'            => $this->status,
            'invite_id'         => $this->invite_id,
            'status_name'       => $this->status_name ?? '',
            'group_id'          => $this->group_id,
            'group_name'        => $this->customGroup->group_name ?? '',
            'created_at'        => (string)$this->created_at,
            'updated_at'        => (string)$this->updated_at,
        ];
    }
}
