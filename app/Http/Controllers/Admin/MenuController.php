<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\HasBatchOperate;
use App\Http\Resources\Admin\ClientRouteMenuList;
use App\Http\Resources\Admin\ThirdPartyWarehouseConfigInfo;
use App\Services\Admin\MenuService;
use App\Services\Admin\ThirdPartyWarehouseConfigService;
use App\Services\ApiResponseService;
use App\Services\ThirdPartyWarehouse\ThirdPartyWarehouseService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MenuController extends Controller
{
    use HasBatchOperate;

    protected MenuService $service;

    public function __construct(MenuService $service)
    {
        $this->service = $service;
    }

    /**
     * 获取客户端菜单树
     */
    public function getClientMenuTree()
    {
        return ClientRouteMenuList::collection($this->service->getClientMenuTree())->additional(ApiResponseService::success());
    }

    public function updateClientMenuTree(Request $request)
    {
        if ($this->service->updateClientMenuTree($request->all())) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage('操作失败');
    }

}
