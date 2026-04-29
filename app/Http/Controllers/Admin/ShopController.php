<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ShopGroupList;
use App\Http\Resources\ShopList as ListResource;
use App\Models\ShopModel;
use App\Services\Admin\ShopService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Class ShopController
 * @package App\Http\Controllers\Admin
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/11/24 17:58
 */
class ShopController extends Controller
{
    public ShopService $service;
    public function __construct(ShopService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function index()
    {
        return ListResource::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function update($id)
    {
        if($this->service->update($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function getGroups()
    {
        return ShopGroupList::collection($this->service->getGroups())
            ->additional(ApiResponseService::success());
    }

    public function addGroup()
    {
        if($this->service->addGroup()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function updateGroup()
    {
        if($this->service->updateGroup()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function deletesGroup()
    {
        if ($this->service->deletesGroup()) {
            return ApiResponseService::successMessage('删除成功');
        }
        return ApiResponseService::errorMessage('删除失败');
    }

    public function batchEditShopTax()
    {
        if($this->service->batchEditShopTax()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 获取店铺平台类型列表
     * @return array
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2024/11/18 20:13
     */
    public function getPlatformList()
    {
        $list = $this->service->getPlatformList();
        return ApiResponseService::success($list);
    }

    public function syncOrder($id)
    {
        if($this->service->syncOrder($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function manualSendEmail($id)
    {
        if ($data = $this->service->manualSendEmail($id)) {
            $message = "操作成功，发送成功：{$data['success_count']}条，发送失败：{$data['fail_count']}条，跳过：{$data['skip_count']}条。";
            return ApiResponseService::success($message, $data);
        }
        return ApiResponseService::error();
    }

    public function getShopSetting($id)
    {
        return ApiResponseService::success($this->service->getShopSetting($id));
    }


    public function saveShopSetting($id, Request $request)
    {
        if($this->service->saveShopSetting($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

}
