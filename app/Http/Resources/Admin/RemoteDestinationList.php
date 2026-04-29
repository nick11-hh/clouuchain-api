<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class RemoteDestinationList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'remote_type' => $this->remoteType,
            'country_id' => $this->country_id,
            'country' => $this->country,
            'city' => $this->city,
            'start_postcode' => $this->start_postcode,
            'end_postcode' => $this->end_postcode,
            'source' => $this->source,
            'source_name' => $this->source_name,
            'grade' => $this->grade,
            'operator_id' => $this->operator_id,
            'operator_name' => $this->operator_name,
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at,
        ];
    }
}
