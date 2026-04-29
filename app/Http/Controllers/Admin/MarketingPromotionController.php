<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MarketingList;
use App\Http\Resources\Admin\PromotionRecordList;
use App\Services\Admin\MarketingPromotionService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketingPromotionController extends Controller
{
    public MarketingPromotionService $service;

    public function __construct(MarketingPromotionService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return MarketingList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function record()
    {
        return PromotionRecordList::collection($this->service->record())
            ->additional(ApiResponseService::success());
    }

    public function withdrawTypeList()
    {
        $data = $this->service->withdrawTypeList();
        return ApiResponseService::success($data);
    }

    public function withdraw($customId, Request $request)
    {
        $data = $this->service->withdraw($customId, $request->all());
        return ApiResponseService::success($data);
    }
}
