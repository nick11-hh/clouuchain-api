<?php

/**
 * @Author: h9471
 * @Created: 2019/9/11 11:41
 */

namespace App\Http\Resources\Client;

use App\Http\Resources\CommonOriginNameList;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpressLineInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'props' => CommonOriginNameList::collection($this->props),
            'labels' => CommonOriginNameList::collection($this->labels),
            'regions' => ExpressLineRegionWithCountryInfo::collection($this->regions),
            'self_pickup_stations' => SelfPickupStationInfo::collection($this->selfPickupStations),
            'default_station' => SelfPickupStationInfo::make($this->defaultStation),
            'first_weight' => $this->first_weight,
            'min_weight' => $this->min_weight,
            'max_weight' => $this->max_weight,
            'has_factor' => $this->has_factor,
            'factor' => $this->factor,
            'mode' => $this->mode,
            'base_mode' => $this->base_mode,
            'is_great_value' => $this->is_great_value,
            'remark' => $this->remark ?? '',
            'need_clearance_code' => $this->need_clearance_code,
            'need_id_card' => $this->need_id_card,
            'clearance_code_remark' => $this->clearance_code_remark,
            'is_delivery' => $this->is_delivery,
            'default_pickup_station_id' => $this->default_pickup_station_id ?? '',
            'should_auto_delivery' => $this->should_auto_delivery,
            'no_throw_condition' => $this->no_throw_condition,
            'is_avg_weight' => $this->is_avg_weight,
            'rule_fee_mode' => $this->rule_fee_mode,
            'max_rule_fee' => $this->max_rule_fee,
            'rule_remark' => $this->rule_remark ?? '',
            'weight_factor' => $this->weight_factor ?? 0,
            'weight_trans' => $this->weight_trans ?? 0,
            'created_at' => (string)$this->created_at,
            'tips' => $this->tips,
            'code' => $this->code,
            'range' => $this->range,
        ];
    }
}
