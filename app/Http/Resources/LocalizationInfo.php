<?php

/**
 * @Author: h9471
 * @Created: 2019/11/13 11:41
 */

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LocalizationInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'weight_name' => $this->weight_name ?? '千克 (KG)',
            'weight_symbol' => $this->weight_symbol ?? 'KG',
            'currency_name' => $this->currency_name ?? '人民币 (CNY)',
            'currency_symbol' => $this->currency_symbol ?? '￥',
            'length_name' => $this->length_name ?? '厘米 (CM)',
            'length_symbol' => $this->length_symbol ?? 'CM',
            'package_express_line' => $this->package_express_line ?? 0,
            'currency' => $this->currency ?? 'CNY',
        ];
    }
}
