<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use App\Services\Client\ApplicationService;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public ApplicationService $service;

    public function __construct(ApplicationService $service)
    {
        $this->service = $service;
    }

    public function create(Request $request)
    {
        if($this->service->create($request)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function getAuthInfo()
    {
        return ApiResponseService::success($this->service->getAuthInfo());
    }
}
