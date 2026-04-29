<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid ?? '',
            'name' => $this->name,
            'email' => $this->email ?? '',
            'whatsapp' => $this->whatsapp ?? '',
            'avatar' => $this->avatar,
            'user_group' => $this->group ? [
                'id' => $this->group->id,
                'name_cn' => $this->group->name_cn,
                'name_en' => $this->group->name_en,
            ] : [],
            'phone' => $this->phone ?? '',
            'last_login_at' => (string) $this->last_login_at,
            'forbid_login' => $this->forbid_login,
            'invitor' => $this->invitor->name ?? '',
            'invite_count' => $this->inviteUser->count(),
            'is_agent_invite' => $this->is_agent_invite,
            'source' => $this->source,
            'user_source' => $this->user_source ?? '',
            'balance' => is_numeric($this->balance) ? $this->balance / 100 : $this->balance->balance / 100,
            'credit' => 0,
            'created_at' => (string) $this->created_at,
            'point' => $this->member->point ?? 0,
            'growth_value' => $this->member->growth_value ?? 0,
            'member_level_id' => $this->member->level_id ?? null,
            'member_level_name' => $this->member->level->name ?? '',
            'customer_name' => $this->customer->name ?? '',
            'sale_name' => $this->sale->name ?? '',
            'order_count' => $this->orders_count,
            'tags' => $this->tags,
            'invite_type' => $this->invite_type,
            'invite_id' => $this->invite_id,
            'invite_uid' => $this->invitor->uid ?? '',
            'channel' => ($this->invite_type == 'channels') ? $this->channel : null,
            'consume_amount' => $this->consume_amount / 100,
            'username' => $this->username,
            'packages_wait_in_count' => $this->packages_wait ?? 0,
            'packages_already_in_count' => $this->packages_in ?? 0,
            'packages_count' => $this->packages_count ?? 0,
        ];
    }
}
