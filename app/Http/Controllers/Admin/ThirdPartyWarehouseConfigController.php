<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\HasBatchOperate;
use App\Http\Resources\Admin\ThirdPartyWarehouseConfigInfo;
use App\Services\Admin\ThirdPartyWarehouseConfigService;
use App\Services\ApiResponseService;
use App\Services\ThirdPartyWarehouse\ThirdPartyWarehouseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ThirdPartyWarehouseConfigController extends Controller
{
    use HasBatchOperate;

    protected ThirdPartyWarehouseConfigService $service;

    public function __construct(ThirdPartyWarehouseConfigService $service)
    {
        $this->service = $service;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function index(Request $request): array
    {
        return ApiResponseService::success($this->service->index());
    }

    public function update(Request $request)
    {
        if ($this->service->update($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    public function statusUpdate(Request $request)
    {
        if ($this->service->statusUpdate($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    public function enableConfig()
    {
        if ($enableConfig = $this->service->enableConfig()) {
            return ThirdPartyWarehouseConfigInfo::make($enableConfig)->additional(ApiResponseService::success());
        }
        return ApiResponseService::success();
    }

    public function testApi(Request $request)
    {

        try {
            
            $this->service->testApi($request->all());

            return ApiResponseService::successMessage('API 配置成功');

        } catch (\Exception $e) {
            
            return ApiResponseService::errorMessage($e->getMessage());
        }
    }
}
