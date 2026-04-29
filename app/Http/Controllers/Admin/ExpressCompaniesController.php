<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChannelsList;
use App\Http\Resources\ExpressCompaniesList as ListResource;
use App\Http\Resources\LogisticsChannelList;
use App\Lib\Code;
use App\Services\Admin\ExpressCompaniesService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class ExpressCompaniesController extends Controller
{
    public ExpressCompaniesService $service;

    public function __construct(ExpressCompaniesService $service)
    {
        $this->service = $service;
    }

    public function index()
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

    /**
     * 更新启用状态
     * @param Request $request
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     * @author DonnyLiu <2365057581@qq.com>
     * @date 2025/1/10 18:40
     */
    public function updateEnable(Request $request)
    {
        $this->service->updateEnable($request->all());
        return ApiResponseService::success();
    }

    public function channels()
    {
        return LogisticsChannelList::collection($this->service->channels())
            ->additional(ApiResponseService::success());
    }

    public function place()
    {
        if($this->service->place()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function getLabel($id)
    {
        if($this->service->getLabel($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error(Code::OPERATE_FAIL,'获取面单失败');
    }

    public function tracking($id)
    {
        if($this->service->tracking($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function channelsByCompanes($express_companies_id)
    {
        return ChannelsList::collection($this->service->channelsByCompanes($express_companies_id))
            ->additional(ApiResponseService::success());
    }

    // 获取物流商已启用的渠道
    public function enabledChannelsList($express_companies_id)
    {
        return ChannelsList::collection($this->service->enabledChannelsList($express_companies_id))
            ->additional(ApiResponseService::success());
    }

    public function enableChannel()
    {
        if($this->service->enableChannel()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function getDsConsignment($order_id)
    {
        if($this->service->getDsConsignment($order_id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function refreshChannels()
    {
        if($this->service->refreshChannels()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function getAuthorization()
    {
        return ApiResponseService::success($this->service->getAuthorization());
    }

}
