<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConsultList as ListResoult;
use App\Services\Admin\ConsultService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConsultController extends Controller
{
    public ConsultService $service;
    public function __construct(ConsultService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        return ListResoult::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * @return array|JsonResponse
     */
    public function store(): JsonResponse|array
    {
        if($this->service->store()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function show($order_id)
    {
        $res = $this->service->show($order_id);
        if(empty($res)) {
            return ApiResponseService::success();
        }

        return ListResoult::make($res)
            ->additional(ApiResponseService::success());
    }

    /**
     * 标记信息为已读
     * @param $id
     * @return array|JsonResponse
     */
    public function mark($id): JsonResponse|array
    {
        if($this->service->mark($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
