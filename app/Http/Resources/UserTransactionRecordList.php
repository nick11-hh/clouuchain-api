<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:41
 */

namespace App\Http\Resources;

use App\Models\CurrencyList;
use App\Models\TransactionRecord;
use Illuminate\Http\Resources\Json\JsonResource;

class UserTransactionRecordList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'serial_no' => $this->serial_no ?? '',
            'type' => $this->type,
            'payment_type' => $this->mod4pay,
            'payment_type_name' => $this->pay_name,
            'order_amount' => $this->order && $this->type != TransactionRecord::ADDITIONAL_FEE
                ? $this->order->actual_payment_fee / 100
                : 0,
            'pay_amount' => $this->amount / 100,
            'coupon_amount' => $this->order ? $this->order->coupon_discount_fee / 100 : 0,
            'order_sn' => $this->order_sn ?? '',
            'order_id' => $this->order ? $this->order->id : '',
            'outer_sn' => $this->out_serial_no ?? '',
            'show_rate'   => bccomp($this->trans_rate, 1, 4) !== 0,
            'rate_amount' => rate_transform($this->amount / 100, $this->trans_rate),
            'currency_symbol' => CurrencyList::getSymbol($this->trans_currency),
            'currency_code' => $this->trans_currency,
            'created_at' => (string) $this->created_at,
            'is_use_point' => $this->is_use_point,
            'point' => $this->point,
            'point_amount' => $this->point_amount / 100,
            'remark' => $this->recordResource?->remark ?? '',
        ];
    }
}
