<?php

namespace App\Services\Client;

use App\Models\CustomInvoiceAddressModel;


class CustomInvoiceAddressService extends BaseService
{

    /**
     * 初始化
     */
    public function __construct()
    {
        $this->model = new CustomInvoiceAddressModel();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    /**
     * 获取发票地址
     */
    public function getInvoiceAddress()
    {
        return $this->model::query()->where('customer_id', getCustomId())->first();
    }

    /**
     * 保存发票地址
     */
    public function saveInvoiceAddress(array $params)
    {
        validator($params, [
            'name' => 'required|string|max:255', // 名称
            'first_name' => 'sometimes|nullable|string|max:255', // 名
            'last_name' => 'sometimes|nullable|string|max:255', // 姓
            'country' => 'sometimes|string|max:255', // 国家
            'world_country_id' => 'sometimes|int', // world_countries.id
            'province' => 'sometimes|nullable|string|max:255', // 省/州
            'city' => 'sometimes|nullable|string|max:255', // 城市
            'address_detail' => 'sometimes|nullable|string|max:255', // 地址详情
            'phone_area_code' => 'sometimes|nullable|string|max:255', // 手机区号
            'phone_number' => 'sometimes|nullable|string|max:255', // 手机号码
            'email' => 'sometimes|nullable|email|max:255', // email
            'postcode' => 'sometimes|nullable|string|max:16', // 邮编
            'tax' => 'sometimes|nullable|string|max:255', // 税号
        ])->validate();

        $this->model::query()->updateOrCreate([
            'customer_id' => getCustomId(),
        ], $this->model::init($params));

        return true;
    }
}
