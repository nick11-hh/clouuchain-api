<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ThirdPartyMultiChannelList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'express_line_id' => $this->express_line_id,
            'expressLine' => $this->expressLine,
            'docking_type' => $this->docking_type,
            'docking_company' => $this->dockingCompany,
            'first_num' => $this->first_num,
            'first_condition' => $this->first_condition,
            'type' => $this->type,
            'second_condition' => $this->second_condition,
            'second_num' => $this->second_num,
            'channel_code' => $this->channel_code
        ];
    }
}
