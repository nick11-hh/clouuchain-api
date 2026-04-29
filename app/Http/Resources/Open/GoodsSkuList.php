<?php
namespace App\Http\Resources\Open;

use Illuminate\Http\Resources\Json\JsonResource;

class GoodsSkuList extends JsonResource
{
    /**
     * @param $request
     * @return array
     */
    public function toArray($request)
    {
        $sale_price = bcdiv($this->purchase_price, 0.9, 4);
        return [
            'id' => $this->id,
            'sku_id' => $this->sku_id,
            'prop_id' => $this->prop_id,// 商品属性ID
            'spec_name' => $this->spec_name,// 商品规格名称
            'spec_info' => $this->spec_info,// 商品规格详情
            'sale_price' => round($sale_price, 2),// 商品销售价格
            'images' => $this->images ?? [],
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'weight' => $this->weight,
        ];
    }
}
