<?php
/**
 * @Author: h9471
 * @date 2020-03-03
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RemoteTypeList;
use App\Services\Admin\RemoteTypeService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Throwable;

class RemoteTypeController extends Controller
{
    protected RemoteTypeService $service;

    public function __construct(RemoteTypeService $service)
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
        return RemoteTypeList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse|array
     * @throws Exception
     * @throws Throwable
     */
    public function store(): JsonResponse|array
    {
        if ($model = $this->service->store(\request()->all())) {
            return ApiResponseService::success(['id' => $model->getKey()]);
        }

        return ApiResponseService::error();
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return RemoteTypeList
     */
    public function show(int $id): RemoteTypeList
    {
        return RemoteTypeList::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param int $id
     * @return JsonResponse|array
     * @throws Exception
     */
    public function update(int $id): JsonResponse|array
    {
        if ($this->service->update($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param Request $request
     * @return JsonResponse|array
     */
    public function destroy(Request $request): JsonResponse|array
    {
        if ($this->service->destroy($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function all(): array
    {
        return ApiResponseService::success($this->service->all());
    }
}
