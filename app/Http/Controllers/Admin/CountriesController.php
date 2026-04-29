<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CountriesList;
use App\Services\Admin\CountriesService;
use App\Services\ApiResponseService;

class CountriesController extends Controller
{

    protected CountriesService $service;

    public function __construct(CountriesService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return CountriesList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function getEnableCountries()
    {
        return CountriesList::collection($this->service->getEnableCountries())
            ->additional(ApiResponseService::success());
    }


}
