<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use App\Http\Resources\Client\UserGroupList;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineAuthInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'auth_target' => $this->auth_target,
            'user_groups' => UserGroupList::collection($this->authUserGroups),
            'member_levels' => MemberLevelList::collection($this->authMemberLevels),
            'user_tags' => UserTagList::collection($this->authUserTags),
            'users' => $this->authUsers
        ];
    }
}
