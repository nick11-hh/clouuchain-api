<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid ?? '',
            'origin_name' => $this->getRawOriginal('name'),
            'name' => $this->name,
            'remark_name' => $this->remark_name ?? '',
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
            'invitor' => $this->invitor->name ?? '',
            'invitor_id' => $this->invite_id ?? '',
            'is_agent_invite' => $this->is_agent_invite,
            'is_agent' => $this->agent ? 1 : 0,
            'invite_count' => $this->inviteUser->count(),
            'source' => $this->user_source ?? '',
            'balance' => is_numeric($this->balance) ? $this->balance / 100 : $this->balance->balance / 100,
            'credit' => 0,
            'addresses' => UserAddressList::collection($this->addresses),
            'profile' => UserProfileInfo::make((object) ($this->profile ?? [])),
            'customer' => $this->customer,
            'sale' => $this->sale,
            'created_at' => (string) $this->created_at,
            'tags' => $this->tags,
            'invite_type' => $this->invite_type,
            'invite_id' => $this->invite_id,
            'invite_uid' => $this->invitor->uid ?? '',
            'channel' => ($this->invite_type == 'channels') ? $this->channel : null,
            'consume_amount' => $this->consume_amount / 100,
            'point' => $this->member->point ?? 0,
            'growth_value' => $this->member->growth_value ?? 0,
            'last_login_ip' => $this->last_login_ip ?? '',
            'register_ip' => $this->register_ip ?? '',
            'username' => $this->username,
        ];
    }
}
