<?php
namespace App\Http\Controllers\Admin;

use App\Http\Resources\Admin\ThirdPartyWarehouseConfigInfo;
use App\Services\Admin\ExpressOrderService;
use App\Services\Admin\ThirdApiService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ExpressOrderController extends Controller
{

    protected ExpressOrderService $service;

    public function __construct(ExpressOrderService $service)
    {
        $this->service = $service;
    }

    public function queryTracking(Request $request)
    {
        if ($this->service->queryTracking($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage('操作失败');
    }

}
