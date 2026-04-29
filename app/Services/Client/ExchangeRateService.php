<?php

namespace App\Services\Client;

use App\Models\ExchangeRateModel;

class ExchangeRateService extends BaseService
{
    public function __construct(ExchangeRateModel $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function getRates()
    {
        $result = $this->query->select(['custom_exchange_rate as rate', 'currency_code'])->orderByDesc('id')->get()->keyBy('currency_code')->toArray();
        $arr = [];
        foreach ($result as $key => $item) {
            $arr[$key] = $item['rate'];
        }

        return $arr;
    }
}
