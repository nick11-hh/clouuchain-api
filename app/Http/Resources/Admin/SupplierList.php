<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierList extends JsonResource
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
            'supplier_name' => $this->supplier_name,
            'supplier_code' => $this->supplier_code,
            'type' => $this->type,
            'type_name' => $this->type_name,
            'supplier_url' => $this->supplier_url,
            'payment_method' => $this->payment_method,
            'payment_method_name' => $this->payment_method_name,
            'payment_bank' => $this->payment_bank,
            'payment_account' => $this->payment_account,
            'payment_remark' => $this->payment_remark,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'contact_address' => $this->contact_address,
            'remark' => $this->remark,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'supplier_qualification' => $this->supplier_qualification,
            'main_category' => $this->main_category,
            'certifications' => $this->certifications,
            'shipping_address' => $this->shipping_address,
            'payment_terms' => $this->payment_terms,
            'cooperation_level' => $this->cooperation_level,
            'cooperation_level_name' => $this->cooperation_level_name,
            'service_and_after_sales' => $this->service_and_after_sales,
            'tax_point' => $this->tax_point,
            'face_value' => $this->face_value,
            'overall_rating' => $this->overall_rating,
            'overall_rating_name' => $this->overall_rating_name,
            'person_in_charge' => $this->person_in_charge,
            'person_in_charge_role' => $this->person_in_charge_role,
            'contact_info' => $this->contact_info,
            'factory_images' => $this->factory_images,
            'factory_scale' => $this->factory_scale,
            'factory_scale_name' => $this->factory_scale_name,
            'developer' => $this->developer,
            'developer_name' => $this->admin->name ?? '',
            'visit_times' => $this->visit_times,
            'created_at' => (string)$this->created_at
        ];
    }
}
