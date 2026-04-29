<?php

namespace App\Http\Controllers\Client;

use App\Services\Client\ExpressOrderService;
use App\Services\Admin\ThirdApiService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * 快递订单控制器
 * Class ExpressOrderController
 * @package App\Http\Controllers\Client
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/11/8 18:18
 */
class ExpressOrderController extends Controller
{
    protected ExpressOrderService $service;

    public function __construct(ExpressOrderService $service)
    {
        $this->service = $service;
    }

    /**
     * 查询物流信息
     * @param Request $request
     * @return array
     * @throws ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/8 18:47
     */
    public function getTrackingInfo(Request $request)
    {
        return ApiResponseService::success($this->service->getTrackInfo($request->all()));
    }
}
