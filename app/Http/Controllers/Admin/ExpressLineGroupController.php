<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:38
 */

namespace App\Http\Controllers\Admin;

use App\Http\Resources\ExpressLineGroupList as ListResources;
use App\Services\Admin\ExpressLineGroupService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class ExpressLineGroupController extends Controller
{
    protected ExpressLineGroupService $service;

    public function __construct(ExpressLineGroupService $service)
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
        return ListResources::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse|array
    {
        if ($this->service->create($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * update
     *
     * @param $id
     * @param Request $request
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function update($id, Request $request): JsonResponse|array
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * Display the specified resource.
     *
     * @param $id
     * @return ListResources
     */
    public function show($id): ListResources
    {
        return ListResources::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param $id
     * @return JsonResponse|array
     * @throws Exception
     */
    public function destroy($id): JsonResponse|array
    {
        if ($this->service->destroy($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 开关
     *
     * @param $id
     * @param int $status
     * @return JsonResponse|array
     * @throws Exception
     */
    public function groupsStatus($id, int $status): JsonResponse|array
    {
        if ($this->service->groupsStatus($id, (bool) $status)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 复制
     *
     * @param $id
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function groupsCopy($id): JsonResponse|array
    {
        if ($this->service->groupsCopy($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param $id
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function updateTrans($id): JsonResponse|array
    {
        if ($this->service->updateTranslateData($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
