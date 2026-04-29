<?php

namespace App\Services\Admin;

use App\Models\Country;

class CountriesService extends BaseService
{
    public function __construct()
    {
        $this->model = new Country();
        $this->formData = request()->all();
        $this->query = $this->model->newQuery();
        $this->setFilterRules();
    }

    public function getEnableCountries()
    {
        return $this->model::query()->where('enabled', 1)->get();
    }
}
