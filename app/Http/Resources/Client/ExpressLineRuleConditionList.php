<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources\Client;

use App\Http\Resources\CommonJsonNameList;
use App\Http\Resources\CommonOriginNameList;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineRuleConditionList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'param' => $this->param,
            'param_name' => $this->paramName,
            'comparison' => $this->comparison,
            'value' => $this->value / 1000,
            'address_tags' => CommonJsonNameList::collection($this->userAddressTags),
            'remote_types' => CommonJsonNameList::collection($this->remoteTypes),
            'created_at' => (string) $this->created_at,
        ];
    }
}
