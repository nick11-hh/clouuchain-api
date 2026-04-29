<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class GoodsList extends JsonResource
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
            'id' => $this->id,
            'spu' => $this->spu,
            'goods_name' => $this->goods_name,
            'goods_name_cn' => $this->goods_name_cn,
            'category_id' => $this->category_id,
            'category_name' => $this->category->name ?? '',
            'brand' => $this->brand,
            'unit' => $this->unit,
            'purchase_price' => $this->purchase_price,
            'purchase_url' => $this->purchase_url,
            'cover_image' => $this->cover_image,
            'props' => $this->props,
            'goods_lowest_price' => $this->goods_lowest_price,
            'sale_count' => $this->sale_count,
            'status' => $this->status,
            'status_1688' => $this->status_1688,
            'status_name' => $this->status_name,
            'origin_type' => $this->origin_type,
            'goods_audit' => [
                'audit_status'=>$this->goodsAudit->audit_status??'',
                'audit_status_name'=>$this->goodsAudit->audit_status_name??'',
                'audit_user_name'=>$this->goodsAudit->audit_user_name??'',
                'audit_time'=>$this->goodsAudit->audit_time??'',
                'audit_remark'=>$this->goodsAudit->audit_remark??'',
                'commit_status'=>$this->goodsAudit->commit_status??'',
                'commit_status_name'=>$this->goodsAudit->commit_status_name??'',
                'commit_user_name'=>$this->goodsAudit->commit_user_name??'',
                'commit_time'=>$this->goodsAudit->commit_time??'',
            ],
            'is_hot' => $this->is_hot,
            'skus_count' => $this->skus_count,
            'developer'     => [
                'id'        =>  $this->developer->id ?? '',
                'name'      =>  $this->developer->name ?? '系统',
            ],
            'max_purchase_price'     => (float) $this->max_purchase_price,
            'min_purchase_price'     => (float) $this->min_purchase_price,
            'max_sale_price'         => (float) $this->max_sale_price,
            'min_sale_price'         => (float) $this->min_sale_price,
            // 'skus' => GoodsSkuList::collection($this->skus),
            'created_at' => (string)$this->created_at,
            'goods_type' => $this->goods_type ?? 1,
            'goods_type_name' => $this->goods_type_name ?? '',
            'packing_materials_type' => $this->packing_materials_type ?? 0,
            'packing_materials_type_name' => $this->packing_materials_type_name ?? '',
            'options' => $this->options,
            'wait_push_to_mabang_count' => $this->wait_push_to_mabang_count,
            'self_goods' => $this->self_goods,
        ];
    }
}
