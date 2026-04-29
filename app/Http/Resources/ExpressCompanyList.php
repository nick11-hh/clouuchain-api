<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExpressCompanyList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'num' => $this->num,
            'name' => $this->name,
            'status' => $this->status,
            'auto_status' => $this->auto_status,
            'index' => $this->index,
            'config' => $this->crawlerConfig,
        ] + $this->getTranslatedLocalesDiffAll()->toArray();
    }
}
