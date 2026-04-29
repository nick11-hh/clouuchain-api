<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResourceList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        $countryIds = $this->country_ids ?? [];
        if (!empty($countryIds)) {
            foreach ($countryIds as &$value) {
                $value = (int)$value;
            }
        }

        return [
            'id'           => $this->id,
            'customer_id'  => $this->customer_id,
            'imgs'         => $this->imgs,
            'product_name' => $this->product_name,
            'url'          => $this->url,
            'target_price' => $this->target_price,
            'status'       => $this->status,
            'price'        => $this->price,
            'remark'       => $this->remark,
            'desc'         => $this->desc,
            'status_name'  => $this->status_name,
            'customer'     => $this->customer ?? '',
            'purchaser'    => $this->procure,
            'selectInfo'   => $this->product,
            'unread'       => $this->unread,
            'created_at'   => (string)$this->created_at,
            'updated_at'   => (string)$this->updated_at,
            'country_ids'  => $countryIds,
        ];
    }
}
