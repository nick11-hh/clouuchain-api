<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomInvoiceAddressModel extends Model
{

    protected $table = 'dsp_custom_invoice_address';

    protected $fillable = ['customer_id', 'name', 'first_name', 'last_name', 'country', 'province', 'city', 'address_detail', 'phone_area_code', 'phone_number', 'email', 'postcode', 'tax', 'world_country_id'];

    public static function init($params): array
    {
        return [
            'customer_id' => $params['customer_id'] ?? getCustomId(),
            'name' => $params['name'] ?? '', // 名称
            'first_name' => $params['first_name'] ?? '', // 名
            'last_name' => $params['last_name'] ?? '', // 姓
            'country' => $params['country'] ?? '', // 国家
            'world_country_id' => $params['world_country_id'] ?? 0, // world_countries.id
            'province' => $params['province'] ?? '', // 省/州
            'city' => $params['city'] ?? '', // 城市
            'address_detail' => $params['address_detail'] ?? '', // 地址详情
            'phone_area_code' => $params['phone_area_code'] ?? '', // 手机区号
            'phone_number' => $params['phone_number'] ?? '', // 手机号码
            'email' => $params['email'] ?? '', // email
            'postcode' => $params['postcode'] ?? '', // 邮编
            'tax' => $params['tax'] ?? '', // 税号
        ];
    }

    public function custom()
    {
        return $this->belongsTo(Custom::class, 'customer_id', 'id');
    }
}
