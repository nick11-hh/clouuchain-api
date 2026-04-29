<?php


namespace App\Http\Resources\Client;


use Illuminate\Http\Resources\Json\JsonResource;

class TransferRecordList extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'serial_no' => $this->serial_no,
            'apply_amount' => number_format($this->apply_amount / 100, 2),
            'confirm_amount' => number_format($this->confirm_amount / 100, 2),
            'apply_images' => $this->apply_images ?? [],
            'apply_remark' => $this->apply_remark,
            'pay_account' => $this->pay_account,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'created_at' => (string) $this->created_at,
            'custom' => [
                'id' => $this->custom_id ?? '',
                'custom_name' => $this->custom->custom_name ?? '',
            ],
            'payment_type' => [
                'id' => $this->paymentType->id ?? '',
                'name' => $this->paymentType->name ?? '',
                'currency' => $this->paymentType->currency ?? '',
            ],
        ];
    }
}
