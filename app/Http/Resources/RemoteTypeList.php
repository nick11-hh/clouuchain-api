<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RemoteTypeList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'remark' => $this->remark,
            'source' => $this->source,
            'source_name' => $this->source_name,
            'operator_id' => $this->operator_id,
            'operator_name' => $this->operator_name,
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at,
        ];
    }
}
