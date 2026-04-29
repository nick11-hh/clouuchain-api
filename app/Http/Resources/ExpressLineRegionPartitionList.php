<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineRegionPartitionList extends JsonResource
{
    public function toArray($request)
    {
       return [
           'id' => $this->id,
           'name' => $this->name,
           'children' => ChildrenAreaList::collection($this->areas),
       ];
    }
}
