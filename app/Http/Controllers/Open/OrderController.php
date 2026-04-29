<?php

namespace App\Http\Controllers\Open;

use App\Exceptions\AccidentException;
use App\Http\Controllers\Controller;
use App\Lib\Code;
use App\Services\ApiResponseService;
use App\Services\Open\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    private $service;
    public function __construct(OrderService $orderService)
    {
        $this->service = $orderService;
    }

    public function push(Request $request)
    {
        try {
            $result = $this->service->push($request->all());
            if ($result) {
                return ApiResponseService::success($result, Code::SUCCESS, '推送成功');
            }
            return ApiResponseService::errorMessage('推送失败');
        } catch (AccidentException $e) {
            return ApiResponseService::errorMessage($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return ApiResponseService::errorMessage('系统错误: ' . $e->getMessage(), Code::SERVER_ERROR);
        }
    }

    /**
     * 获取订单状态
     *
     * @return array|JsonResponse
     */
    public function getOrderInfo(Request $request)
    {
        try {
            $result = $this->service->getOrderInfo($request->all());
            return ApiResponseService::success($result, Code::SUCCESS, '获取成功');
        } catch (AccidentException $e) {
            return ApiResponseService::errorMessage($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return ApiResponseService::errorMessage('系统错误: ' . $e->getMessage(), Code::SERVER_ERROR);
        }
    }

    /**
     * 获取物流信息
     *
     * @return array|JsonResponse
     */
    public function getLogisticInfo(Request $request)
    {
        try {
            $result = $this->service->getLogisticInfo($request->all());
            return ApiResponseService::success($result, Code::SUCCESS, '获取成功');
        } catch (AccidentException $e) {
            return ApiResponseService::errorMessage($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return ApiResponseService::errorMessage('系统错误: ' . $e->getMessage(), Code::SERVER_ERROR);
        }
    }
}
