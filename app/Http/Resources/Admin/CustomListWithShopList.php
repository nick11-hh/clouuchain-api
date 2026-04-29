<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomListWithShopList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $shopList = $this->shopList->filter(function ($item) {
                return $item->order_count > 0; // 只保留 order_count 大于 0 的项
            })
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'customer_id' => $item->customer_id,
                    'name' => $item->shop_name ?? '',
                ];
            })->prepend(['id' => -1, 'customer_id' => $this->id, 'name' => '全部']);

        return [
            'id' => $this->id,
            'name' => "{$this->custom_name}({$this->customer_number})",
            'shop_list' => $shopList,
        ];
    }
}
