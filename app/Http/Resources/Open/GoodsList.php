<?php
namespace App\Http\Resources\Open;

use App\Models\ExchangeRateModel;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsList extends JsonResource
{
    /**
     * @param $request
     * @return array
     */
    public function toArray($request)
    {
        $exchange_rates = ExchangeRateModel::query()->where('currency_code', 'CNY')
            ->value('custom_exchange_rate');
        $purchase_price = bcdiv(bcmul($this->purchase_price, $exchange_rates, 4), 0.9, 4);
        return [
            'id' => $this->id,
            'spu' => $this->spu,
            'goods_name' => $this->goods_name,
            'category_id' => $this->category_id,
            'category_name' => $this->category->name ?? '',
            'brand' => $this->brand,// 品牌
            'unit' => $this->unit,// 单位
            'cover_image' => $this->cover_image,// 封面图
            'props' => $this->props,// 属性
            'purchase_price' => round($purchase_price, 2),
            'sale_count' => $this->sale_count,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'is_hot' => $this->is_hot,
            'created_at' => (string)$this->created_at,
            'updated_at' => (string)$this->updated_at,
            'deleted_at' => (string)$this->deleted_at,
            'goods_type' => $this->goods_type,
            'packing_materials_type' => $this->packing_materials_type,
        ];
    }
}
