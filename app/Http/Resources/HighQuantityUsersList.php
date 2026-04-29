<?php
/**
 * @Author: CB
 * @Created: 2023/05/06
 */

namespace App\Http\Resources;


use Illuminate\Http\Resources\Json\JsonResource;

class HighQuantityUsersList extends JsonResource
{
    public function toArray($request) {
        $last_pay_time = $this->records->sortByDesc('created_at')->first()->created_at ?? '-';
        return [
            'id' => $this->id,
            'uid' => $this->uid ?? '',
            'name' => $this->name,
            'email' => $this->email ?? '',
            'avatar' => $this->avatar,
            'user_group' => [
                'id' => $this->group->id,
                'name_cn' => $this->group->name_cn,
                'name_en' => $this->group->name_en,
            ],
            'phone' => $this->phone ?? '',
            'last_login_at' => (string) $this->last_login_at,
            'forbid_login' => $this->forbid_login,
            'invitor_name' => $this->invitor->name ?? '',
            'invite_count' => $this->inviteUser->count(),
            'is_agent_invite' => $this->is_agent_invite,
            'source' => $this->source,
            'user_source' => $this->user_source ?? '',
            'created_at' => (string) $this->created_at,
            'point' => $this->member->point ?? 0,
            'order_count' => $this->orders_count,
            'tags' => $this->tags,
            'records_sum_amount' => $this->records_sum_amount / 100,
            'invite_type' => $this->invite_type,
            'invite_id' => $this->invite_id,
            'last_pay_time' => date('Y-m-d H:i:s', strtotime($last_pay_time)),
            'channel' => ($this->invite_type == 'channels') ? $this->channel : null,
            'consume_amount' => $this->consume_amount / 100,
        ];
    }
}
