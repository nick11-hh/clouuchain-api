<?php
namespace App\Http\Controllers\Admin;

use App\Http\Resources\Admin\ThirdPartyWarehouseConfigInfo;
use App\Services\Admin\ThirdApiService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ApiServiceController extends Controller
{

    protected ThirdApiService $service;

    public function __construct(ThirdApiService $service)
    {
        $this->service = $service;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function getTrackingConfig(Request $request): array
    {
        return ApiResponseService::success($this->service->getTrackingConfig());
    }

    public function updateTrackingConfig(Request $request)
    {
        if ($this->service->updateTrackingConfig($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    public function updateTrackingStatus(Request $request)
    {
        if ($this->service->updateTrackingStatus($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage('操作失败');
    }

}
