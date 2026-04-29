<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalePriceInfo;
use App\Http\Resources\SalePriceList;
use App\Services\Admin\SalePriceService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class SalePriceController extends Controller
{
    protected $service;

    public function __construct(SalePriceService $service)
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
        return SalePriceList::collection($this->service->index())
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
     * Display the specified resource.
     *
     * @param  int  $id
     * @return array
     */
    public function show(int $id): array
    {
        return ApiResponseService::success(SalePriceInfo::make($this->service->show($id)));
    }

    /**
     * 更新
     * @param Request $request
     * @param int $id
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function update(Request $request, int $id): JsonResponse|array
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 删除
     *
     * @param int $id
     * @return JsonResponse|array
     */
    public function destroy(int $id): JsonResponse|array
    {
        if ($this->service->destroy($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 设置状态
     *
     * @param int $id
     * @param int $status
     * @return JsonResponse|array
     */
    public function setStatus(int $id, int $status): JsonResponse|array
    {
        if ($this->service->setStatus($id, (bool) $status)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function copy(int $id, Request $request): JsonResponse|array
    {
        if ($this->service->copy($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return array
     */
    public function expressGroupList(): array
    {
        return ApiResponseService::success($this->service->expressGroupList());
    }
}
