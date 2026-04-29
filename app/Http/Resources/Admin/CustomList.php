<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Lib\Language;

class CustomList extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'custom_name' => $this->custom_name,
            'custom_email' => $this->custom_email,
            'custom_phone' => $this->custom_phone,
            'phone_area_code' => $this->phone_area_code ?? '',
            'status' => $this->status,
            'is_count' => $this->is_count,
            'commission_rate' => $this->commission_rate,
            'commission_amount' => $this->commission_amount,
            'promotion_id' => $this->promotion_id,
            'status_name' => $this->status_name ?? '',
            'group_id' => $this->group_id,
            'group_name' => $this->customGroup->group_name ?? '',
            'consume_amount' => $this->consume_amount,
            'inviter_name' => $this->inviter->custom_name ?? '',
            'created_at' => (string)$this->created_at,
            'balance' => (string)bcdiv($this->balance->balance, 100, 2),
            'last_login_at' => (string)($this->mainUser->last_login_at ?? ''),
            'credit_line' => $this->credit_line,
            'residual_credit' => $this->residual_credit,
            'frozen_limit' => $this->frozen_limit,
            'cumulative_frozen' => $this->cumulative_frozen,
            'cumulative_unfrozen' => $this->cumulative_unfrozen,
            'cumulative_top_up' => $this->cumulative_top_up,
            'is_auto_payment' => $this->config->is_auto_payment ?? 0, //自动支付
            'staff_name' => $this->staff->name ?? '',
            'default_language' => $this->default_language ?? '',
            'default_language_name' => Language::getLanguageName($this->default_language ?? ''),
            'main_user_id' => $this->main_user_id ?? '',
            'remark' => $this->remark ?? '',
            'customer_number' => $this->customer_number ?? '',
            'goods_once_price' => $this->goods_once_price,
            'customs_quote_config' => CustomsQuoteConfigResource::make($this->customsQuoteConfig),
            'assign_data_permissions' => $this->whenLoaded('assignDataPermissions'),
            'business_license' => $this->oauthClients->license_url ?? '',
        ];
    }
}
