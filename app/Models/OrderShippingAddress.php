<?php

namespace App\Models;

class OrderShippingAddress extends Model
{

    protected $table = 'dsp_shop_order_shipping_address';

    public function remoteDestination()
    {
        $this->hasOne(RemoteDestination::class, 'country_code', 'country_code');
    }

    public static function init($data, $order_id)
    {
        return [
            'order_id'      => $order_id,
            'first_name'    => trim($data['first_name'] ?? ''),
            'last_name'     => trim($data['last_name'] ?? ''),
            'address1'      => trim($data['address1'] ?? ''),
            'address2'      => trim($data['address2'] ?? ''),
            'phone'         => $data['phone'] ?? '',
            'city'          => $data['city'] ?? '',
            'zip'           => $data['zip'] ?? '',
            'province'      => trim($data['province'] ?? ''),
            'country'       => trim($data['country'] ?? ''),
            'company'       => trim($data['company'] ?? ''),
            'latitude'      => $data['latitude'] ?? '',
            'longitude'     => $data['longitude'] ?? '',
            'name'          => trim($data['name'] ?? ''),
            'country_code'  => $data['country_code'] ?? '',
            'province_code' => $data['province_code'] ?? '',
            'email'         => $data['email'] ?? '',
            'tax'           => $data['tax'] ?? '',
            'is_billing_address' => $data['is_billing_address'] ?? 0,
        ];
    }

    /**
     * 过滤指定字段
     * @param $filterField
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/13 20:32
     */
    public static function fieldName($filterField = []): array
    {
        $data = [
            'first_name'   => __('名'),
            'last_name'    => __('姓'),
            'name'         => __('昵称'),
            'country'      => __('国家/地区'),
            'country_code' => __('国家代码'),
            'phone'        => __('电话'),
            'zip'          => __('邮编'),
            'province'     => __('省/州'),
            'city'         => __('城市'),
            'address1'     => __('地址1'),
            'address2'     => __('地址2'),
            'tax'          => __('税号'),
            'email'        => __('邮箱'),
            'company'      => __('企业'),
        ];

        //过滤指定字段
        if (!empty($filterField)) $data = array_diff_key($data, array_flip($filterField));

        return $data;
    }
}
