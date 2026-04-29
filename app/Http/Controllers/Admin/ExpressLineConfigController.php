<?php
/**
 * @Author: h9471
 * @Created: 2021/06/28 15:47
 */

namespace App\Http\Controllers\Admin;

use App\Http\Resources\ExpressLineBasicConfigInfo;
use App\Http\Resources\ExpressLineBillingConfigInfo;
use App\Http\Resources\ExpressLineRuleList;
use App\Services\Admin\ExpressLineConfigService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExpressLineConfigController extends Controller
{
    protected $service;

    public function __construct(ExpressLineConfigService $service)
    {
        $this->service = $service;
    }

    /**
     * @param $id
     * @return array
     */
    public function getBasicConfig($id): array
    {
        return ApiResponseService::success(ExpressLineBasicConfigInfo::make($this->service->getBillingConfig($id)));
    }

    /**
     * 更新基础配置
     * @param $id
     * @param Request $request
     * @return JsonResponse|array
     * @throws Throwable
     */
    public function updateBasicConfig($id, Request $request): JsonResponse|array
    {
        if ($epl = $this->service->updateBasicConfig($id, $request->all())) {
            return ApiResponseService::success($epl);
        }

        return ApiResponseService::error();
    }

    /**
     * 创建基础配置
     * @param Request $request
     * @return JsonResponse|array
     * @throws ValidationException|Throwable
     */
    public function createBasicConfig(Request $request): JsonResponse|array
    {
        if ($epl = $this->service->createBasicConfig($request->all())) {
            return ApiResponseService::success($epl);
        }

        return ApiResponseService::error();
    }

    /**
     * @param $id
     * @param Request $request
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function updateBillingConfig($id, Request $request): JsonResponse|array
    {
        if ($this->service->updateBillingConfig($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param $id
     * @return array
     */
    public function getBillingConfig($id): array
    {
        return ApiResponseService::success(ExpressLineBillingConfigInfo::make($this->service->getBillingConfig($id)));
    }
}
