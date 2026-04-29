<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeRateList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'currency_code'        => $this->currency_code,
            'symbol'               => $this->symbol,
            'exchange_rate'        => $this->exchange_rate,
            'custom_exchange_rate' => $this->custom_exchange_rate,
            'created_at'           => (string)$this->created_at,
            'updated_at'           => (string)$this->updated_at,
        ];
    }
}
