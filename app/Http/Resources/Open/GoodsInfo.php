<?php
namespace App\Http\Resources\Open;

use Illuminate\Http\Resources\Json\JsonResource;

class GoodsInfo extends JsonResource
{
    /**
     * @param $request
     * @return array
     */
    public function toArray($request)
    {
        $purchase_price = bcdiv($this->purchase_price, 0.9, 4);
        return [
            'id' => $this->id,// 商品ID
            'spu' => $this->spu,// 商品SPU
            'goods_name' => $this->goods_name,// 商品名称
            'category_id' => $this->category_id,// 商品分类ID
            'category_name' => $this->category->name ?? '',// 商品分类名称
            'brand' => $this->brand,// 品牌
            'unit' => $this->unit,// 单位
            'cover_image' => $this->cover_image,// 封面图
            'main_images' => $this->main_images ?? [],// 商品主图
            'options' => $this->options ?? [],// 商品规格
            'props' => $this->props,// 商品属性
            'purchase_price' => round($purchase_price, 2),// 商品最低报价=（采购价/90%）
            'sale_count' => $this->sale_count,// 商品销量
            'status' => $this->status,// 状态
            'is_hot' => $this->is_hot,// 是否是热销商品
            'skus' => GoodsSkuList::collection($this->skus),// 商品SKUS
            'detail' => $this->detail,// 商品详情
            'goods_type' => $this->goods_type,// 商品类型 1=商品 2=包材
            'packing_materials_type' => $this->packing_materials_type,// 包材类型 1-包装袋 2-纸箱 3-定制盒子 4-贴纸 5-卡片 99-其他
            'main_video' => $this->main_video,// 商品视频
        ];
    }
}
