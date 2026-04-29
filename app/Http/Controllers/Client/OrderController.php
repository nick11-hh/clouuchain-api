<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\OrderList as ListResource;
use App\Http\Resources\Client\OrderInfo;
use App\Services\ApiResponseService;
use App\Services\Client\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public OrderService $service;
    public function __construct(OrderService $service)
    {
        $this->service = $service;
    }

    public function index(): AnonymousResourceCollection
    {
        return ListResource::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function store()
    {
        if($this->service->store()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function pullPlatformOrders(): array
    {
        if($this->service->pullPlatformOrders()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function askQuote(): array
    {
        if($this->service->askQuote()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function payment()
    {
        if($this->service->payment()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function orderRefund(Request $request)
    {
        if($this->service->orderRefund($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function cancelOrder()
    {
        if($this->service->cancelOrder()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }


    public function fulfillmentRequest($id)
    {
        if($this->service->fulfillmentRequest($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function import()
    {
        return ApiResponseService::success($this->service->import());
    }

    public function orderPayDetail($id)
    {
        $data = $this->service->orderPayDetail($id);
        return OrderInfo::make($data)->additional(ApiResponseService::success());
    }

    public function updateAddress()
    {
        if($this->service->updateAddress()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function payMethod()
    {
        return ApiResponseService::success($this->service->payMethod());
    }

    public function defaultAmount()
    {
        return ApiResponseService::success($this->service->defaultAmount());
    }

    public function deleteItems()
    {
        if ($this->service->deleteItems()) {
            return ApiResponseService::success();
        }
        return ApiResponseService::errorMessage();
    }

    public function statusCount()
    {
        return ApiResponseService::success($this->service->statusCount());
    }

    public function restoreItems()
    {
        if ($this->service->restoreItems()) {
            return ApiResponseService::success();
        }
        return ApiResponseService::errorMessage();
    }

    /**
     * 详情
     * @param $id
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/9/7 15:11
     */
    public function detail($id)
    {
        $data = $this->service->detail($id);
        return ApiResponseService::success($data);
    }

    /**
     * 获取订单id合集
     * @param Request $request
     * @return array
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/12/27 17:02
     */
    public function getIds(Request $request)
    {
        return ApiResponseService::success($this->service->getIds());
    }

    /**
     * 导出订单数据
     */
    public function export()
    {
        return $this->service->export();
    }


    /**
     * @param $id
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function buyAgain($id, Request $request)
    {
        if ($this->service->buyAgain($id, $request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::errorMessage();
    }


}
