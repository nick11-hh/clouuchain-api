<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class stockProcessLogList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'stock_up_id' => $this->stock_up_id,
            'node' => $this->node,
            'approve_id' => $this->approve_id,
            'approval_opinion' => $this->approval_opinion,
            'supervisor' => $this->supervisor,
            'submitter_id' => $this->submitter_id,
            'process' => $this->process,
            'created_at' => $this->created_at,
            'deleted_at' => $this->deleted_at ?? '',
        ];
    }
}
