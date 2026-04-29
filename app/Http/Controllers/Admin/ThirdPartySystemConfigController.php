<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\HasBatchOperate;
use App\Services\Admin\ThirdPartySystemConfigService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ThirdPartySystemConfigController extends Controller
{
    use HasBatchOperate;

    protected ThirdPartySystemConfigService $service;

    public function __construct(ThirdPartySystemConfigService $service)
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

}
