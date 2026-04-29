<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use App\Services\Client\Y1688Service;
use Illuminate\Http\Request;

class Y1688Controller extends Controller
{
    protected $service;

    public function __construct(Y1688Service $service)
    {
        $this->service = $service;
    }

    public function getProductList(Request $request)
    {
        $data = $this->service->getProductList($request->all());
        return ApiResponseService::success($data);
    }

    public function getProductFreight(Request $request)
    {
        $data = $this->service->getProductFreight($request->all());
        return ApiResponseService::success($data);
    }

    public function imageSearch(Request $request)
    {
        $data = $this->service->imageSearch($request->all());
        return ApiResponseService::success($data);
    }

    public function detail($id, Request $request)
    {
        $data = $this->service->detail($id, $request->all());
        return ApiResponseService::success($data);
    }

    public function claim($id)
    {
        $data = $this->service->claim($id);
        return ApiResponseService::success($data);
    }

    public function getAuthUrl()
    {
        return ApiResponseService::success($this->service->getAuthUrl());
    }

    public function auth()
    {
        if($this->service->auth()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function createCrossOrder()
    {
        if($this->service->createCrossOrder()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
