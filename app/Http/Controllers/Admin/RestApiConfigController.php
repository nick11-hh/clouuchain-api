<?php

namespace App\Http\Controllers\Admin;

use App\Services\ApiResponseService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Admin\RestApiConfigService;
use App\Http\Resources\Admin\RestApiConfigListResource;

class RestApiConfigController extends Controller
{
    public RestApiConfigService $service;

    public function __construct(RestApiConfigService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return RestApiConfigListResource::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function store()
    {

        try {

            $res = $this->service->addKey();
            $res->host = 'https://' . request()->getHost();
            return ApiResponseService::success($res);

        } catch (Exception $e) {

            return ApiResponseService::error(message: $e->getMessage());
        }

    }

    public function update($id)
    {

        if($this->service->update($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
    
    public function destroy($id)
    {

        if ($this->service->destroy($id)) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }
}
