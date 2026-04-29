<?php

/**
 * @Author: h9471
 * @Created: 2019/12/10 11:41
 */

namespace App\Http\Resources\Admin;

use App\Models\Currency;
use App\Models\CurrencyList;
use App\Models\ExchangeRateModel;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentSettingInfo extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'pay_logo' => $this->pay_logo ?? '',
            'pay_qrcode' => $this->pay_qrcode ?? '',
            'pay_account' => $this->pay_account ?? '',
            'remark' => $this->remark ?? '',
            'enabled' => $this->enabled,
            'currency' => $this->currency,
            'current_currency' => Currency::current($this->currency),
            'currency_symbol' => CurrencyList::getSymbol($this->currency),
            'created_at' => (string) $this->created_at,
            'payment_setting_connection' => $this->PaymentSettingConnection,
        ];
    }
}
