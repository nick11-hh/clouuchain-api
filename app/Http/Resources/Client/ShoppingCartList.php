<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\GoodsAudit;

class ShoppingCartList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'user_id'          => $this->user_id,
            'custom_id'        => $this->custom_id,
            'platform'         => $this->platform,
            'goods_id'         => $this->goods_id,
            'goods_name'       => $this->goods_name,
            'sku_gross_weight' => $this->sku_gross_weight,
            'spu'              => $this->spu,
            'source_url'       => $this->source_url,
            'cover_image'      => $this->cover_image,
            'skus'             => $this->skus,
            'created_at'       => (string)$this->created_at,
            'updated_at'       => (string)$this->updated_at,
            'goods_type'       => $this->goods_type,
            'self_goods'       => $this->self_goods,
            'audit_status'     => $this->goods->goodsAudit->audit_status ?? GoodsAudit::GOOD_AUDIT_WAITING,
        ];
    }
}
