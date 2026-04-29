<?php

namespace App\Http\Resources\Client;

use App\Models\InvoiceRecords;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class InvoiceList
 * @package App\Http\Resources\Client
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/9/20 10:49
 */
class InvoiceList extends JsonResource
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
            'custom_id' => $this->custom_id,
            'custom_name' => $this->custom->custom_name ?? '',
            'invoice_no' => $this->invoice_no,
            'seller_info' => $this->seller_info ? json_decode($this->seller_info, true) : '',
            'buyer_info' => $this->buyer_info ? json_decode($this->buyer_info, true) : '',
            'total_price' => $this->total_price,
            'storage_url' => $this->storage_url,
            'source_type' => $this->source_type,
            'source_type_name' => $this->source_type_name ?? '-',
            'status' => $this->status,
            'invalid_time' => (string)$this->invalid_time ?? '',
            'created_at' => (string)$this->created_at,
            'file_type' =>$this->file_type,
            'file_type_name' => $this->file_type_name,
        ];
    }
}
