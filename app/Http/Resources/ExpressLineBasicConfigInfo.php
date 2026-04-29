<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use App\Lib\Language;
use App\Models\ExpressLinePrice;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineBasicConfigInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'group_name' => $this->group->name ?? '',
            'group_id' => $this->group_id,
            'name' => $this->name,
            'channel_code' => $this->channel_code,
            'myLogisticsId' => $this->myLogisticsId,
            'myLogisticsChannelId' => $this->myLogisticsChannelId,
            'express_company_id' => $this->express_company_id,
            'express_company_name' => $this->express_company_name,
            'cn_name' => $this->getTranslation('name', Language::CHINESE),
            'en_name' => $this->getTranslation('name', Language::ENGLISH),
            'props' => CommonOriginNameList::collection($this->props),
            'warehouses' => WarehouseAddress::collection($this->warehouses),
            'is_great_value' => $this->is_great_value,
            'icon' => $this->icon,
            'remark' => $this->remark ?? '',
            'need_clearance_code' => $this->need_clearance_code,
            'need_personal_code' => $this->need_personal_code,
            'need_id_card' => $this->need_id_card,
            'is_unique' => $this->is_unique,
            'is_delivery' => $this->is_delivery,
            'default_pickup_station_id' => $this->default_pickup_station_id,
            'labels' => CommonOriginNameList::collection($this->labels),
            'order_mode' => $this->order_mode,
            'require_size' => $this->require_size,
            'tips' => $this->tips,
            'code' => $this->code,
            'prop_mode' => $this->prop_mode,
            'base_mode' => $this->base_mode,//计费模式
            'mode' => $this->mode,//计费价格模式
            'min_weight' => $this->min_weight / 1000,//渠道最小重量
            'range' => $this->range,//开闭区间
            'ceil_weight' => $this->ceil_weight ?? 0,//渠道最小重量
            'weight_rise' => $this->weight_rise ?? 0,//订单单箱打包重量向上取值
            'multi_boxes_ceil' => $this->multi_boxes_ceil ?? 0,//订单多箱打包重量向上取值
            'payment_weight_int' => $this->payment_weight_int ?? 0,//计费重100g以下抹零
            'has_factor' => $this->has_factor,
            'factor' => $this->factor,
            'multi_boxes' => $this->multi_boxes,
            'max_weight' => $this->max_weight / 1000,
            'multi_box_min_weight' => $this->multi_box_min_weight / 1000,
            'is_avg_weight' => $this->is_avg_weight,
            'no_throw_condition'=>$this->no_throw_condition,
            'weight_factor' => $this->weight_factor ?? 0,
            'weight_trans' => $this->weight_trans ?? 0,
            'overweight_status' => $this->overweight_status ?? 0,
            'overweight_weight' => ($this->overweight_weight ?? 0) / 1000,
            'overweight_remark' => $this->overweight_remark ?? '',
        ];
    }
}
