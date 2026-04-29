<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources;

use App\Lib\Language;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineList extends JsonResource
{
    public function toArray($request)
    {
        return [
                'id' => $this->id,
                'cn_name' => $this->getTranslation('name', Language::CHINESE),
                'en_name' => $this->getTranslation('name', Language::ENGLISH),
                'name' => $this->name,
                'ru_name' => $this->getTranslation('name', Language::RUSSIAN),
                'ar_name' => $this->getTranslation('name', Language::ARABIC),
                'pt_name' => $this->getTranslation('name', Language::PORTUGAL),
                'vi_name' => $this->getTranslation('name', Language::VIETNAM),
                'icon' => $this->icon,
                'warehouses' => WarehouseAddress::collection($this->warehouses),
                'countries' => CountryExpressList::collection($this->countries),
                'types' => PackagePropList::collection($this->props),
                'labels' => CommonOriginNameList::collection($this->labels ?? []),
                'reference_time' => $this->reference_time,
                'first_weight' => $this->first_weight / 1000,
                'first_money' => $this->first_money / 100,
                'first_cost_money' => $this->first_cost_money / 100,
                'next_weight' => $this->next_weight / 1000,
                'next_money' => $this->next_money / 100,
                'next_cost_money' => $this->next_cost_money / 100,
                'min_weight' => $this->min_weight / 1000,
                'max_weight' => $this->max_weight / 1000,
                'has_factor' => $this->has_factor,
                'factor' => $this->factor,
                'ceil_weight' => $this->ceil_weight,
                'weight_rise' => $this->weight_rise,
                'multi_boxes' => $this->multi_boxes,
                'multi_boxes_ceil' => $this->multi_boxes_ceil,
                'mode' => $this->mode,
                'base_mode' => $this->base_mode,
                'price_grade' => ExpressLinePriceList::collection($this->priceGrade),
                'enabled' => $this->enabled,
                'is_great_value' => $this->is_great_value,
                'remark' => $this->remark ?? '',
                'need_clearance_code' => $this->need_clearance_code,
                'need_id_card' => $this->need_id_card,
                'need_personal_code' => $this->need_personal_code,
                'clearance_code_remark' => $this->clearance_code_remark,
                'is_delivery' => $this->is_delivery,
                'default_pickup_station_id' => $this->default_pickup_station_id ?? '',
                'should_auto_delivery' => $this->should_auto_delivery,
                'is_unique' => $this->is_unique,
                'docking_type' => $this->docking_type ?: '',
                'express_company_id' => $this->docking_type,
                'express_company_name' => $this->express_company_name,
                'created_at' => (string)$this->created_at,
                'order_mode' => $this->order_mode,
                'multi_box_min_weight' => $this->multi_box_min_weight / 1000,
                'is_avg_weight' => $this->is_avg_weight,
                'no_throw_condition'=>$this->no_throw_condition,
                'rule_fee_mode' => $this->rule_fee_mode,
                'max_rule_fee' => $this->max_rule_fee / 100,
                'channel_code' => $this->channel_code ?? '',
                'push_type' => $this->push_type ?? 1,
                'third_push_now' => $this->third_push_now ?? 0,
                'require_size' => $this->require_size,
                'auth_target' => $this->auth_target,
                'weight_factor' => $this->weight_factor ?? 0,
                'weight_trans' => $this->weight_trans ?? 0,
                'channel_type' => $this->channel_type,
                'auto_sn_express_id' => $this->auto_sn_express_id,
                'auto_sn_mode' => $this->auto_sn_mode,
                'code' => $this->code,
                'docking_mode' => $this->auto_sn_express_id ? 2 : 1,
                'docking_enabled' => (int) ($this->docking_type || $this->auto_sn_express_id),
                'sort' => $this->sort,
            ] + $this->getTranslatedLocalesDiffAll()->toArray();
    }
}
