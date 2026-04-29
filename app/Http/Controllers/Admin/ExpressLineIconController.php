<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\ExpressLineIconList;
use App\Services\Admin\ExpressLineIconService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Throwable;

class ExpressLineIconController extends Controller
{
    protected ExpressLineIconService $service;

    public function __construct(ExpressLineIconService $service)
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
        return ExpressLineIconList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function show(int $id): ExpressLineIconList
    {
        return ExpressLineIconList::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     * @throws Throwable
     */
    public function store(Request $request): JsonResponse|array
    {
        if ($this->service->create($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     *
     *
     * @param  int  $id
     * @param  Request  $request
     * @return JsonResponse|array
     * @throws Exception
     */
    public function update(int $id, Request $request): JsonResponse|array
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param  int  $id
     * @return JsonResponse|array
     * @throws Exception
     */
    public function setDefault(int $id): JsonResponse|array
    {
        if ($this->service->setDefault($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 删除
     * @param  int  $id
     * @return JsonResponse|array
     * @throws Exception
     * @throws Throwable
     */
    public function destroy(int $id): JsonResponse|array
    {
        if ($this->service->delete(Arr::wrap($id))) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return array
     */
    public function getExpressLineIconList(): array
    {
        return ApiResponseService::success($this->service->getSimpleExpressLineIconList()->toArray());
    }
}
