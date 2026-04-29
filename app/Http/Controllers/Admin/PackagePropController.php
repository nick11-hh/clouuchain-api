<?php
/**
 * @Author: h9471
 * @Created: 2019/9/10 11:38
 */

namespace App\Http\Controllers\Admin;

use App\Http\Resources\PackagePropTransList;
use App\Services\Admin\PackagePropService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Throwable;

class PackagePropController extends Controller
{
    protected PackagePropService $service;

    public function __construct(PackagePropService $service)
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
        return PackagePropTransList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * @param int $id
     * @return PackagePropTransList
     */
    public function show(int $id): PackagePropTransList
    {
        return PackagePropTransList::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }
    /**
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     * @throws Throwable
     */
    public function store(Request $request): JsonResponse|array
    {
        if ($this->service->add($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param  int  $id
     * @param  Request  $request
     * @return JsonResponse|array
     * @throws Exception
     * @throws ValidationException
     * @throws Throwable
     */
    public function update(int $id, Request $request): JsonResponse|array
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 自定义排序
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     * @throws ValidationException
     */
    public function sort(Request $request): JsonResponse|array
    {
        if ($this->service->sort($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 删除
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     */
    public function delete(Request $request): JsonResponse|array
    {
        if ($this->service->delete($request->only('DELETE')['DELETE'])) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function updateTrans(int $id): JsonResponse|array
    {
        if ($this->service->updateTranslateData($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
