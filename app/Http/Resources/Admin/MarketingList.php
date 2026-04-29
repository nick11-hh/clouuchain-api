<?php


namespace App\Http\Resources\Admin;


use Illuminate\Http\Resources\Json\JsonResource;

class MarketingList extends JsonResource
{
    public function toArray($request): array
    {
        $agent = $this->agent->toArray();
        $successOrderNums = count($agent);
        $accumulatedCommission = array_sum(array_column($agent, 'commission_amount'));
        $accumulatedWithdrawal = 0;
        foreach ($agent as $item) {
            if ($item['status'] == 3) {
                $accumulatedWithdrawal += $item['commission_amount'];
            }
        }

        return [
            'id'                    => $this->id,
            'custom_name'           => $this->custom_name,
            'custom_phone'          => $this->custom_phone,
            'custom_email'          => $this->custom_email,
            'commission_rate'       => $this->commission_rate,
            'status'                => $this->status,
            'status_name'           => $this->status_name ?? '',
            'group_id'              => $this->group_id,
            'group_name'            => $this->customGroup->group_name ?? '',
            'success_order_nums'    => $successOrderNums,
            'accumulated_commission'=> round($accumulatedCommission, 2),
            'accumulated_withdrawal'=> round($accumulatedWithdrawal, 2),
            'promotion'             => PromotionList::collection($this->promotion),
            'created_at'            => (string)$this->created_at,
            'updated_at'            => (string)$this->updated_at,
        ];
    }
}
