<?php

namespace App\Http\Controllers\Client;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\ApiResponseService;
use App\Services\Client\CustomInvoiceAddressService;

class CustomInvoiceAddressController extends Controller
{
    public CustomInvoiceAddressService $service;

    /**
     * @param CustomInvoiceAddressService $service
     */
    public function __construct(CustomInvoiceAddressService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取发票地址
     *
     **/
    public function getInvoiceAddress()
    {
        return ApiResponseService::success($this->service->getInvoiceAddress());
    }

    /**
     * 保存发票地址
     *
     * **/
    public function saveInvoiceAddress(Request $request)
    {
        return ApiResponseService::success($this->service->saveInvoiceAddress($request->all()));
    }
}
