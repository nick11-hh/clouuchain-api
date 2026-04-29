<?php
/**
 * @Author: h9471
 * @Created: 2019/9/10 11:38
 */

namespace App\Http\Controllers\Admin;

use App\Services\Admin\UserAddressTagService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Throwable;

class UserAddressTagController extends Controller
{
    protected UserAddressTagService $service;

    public function __construct(UserAddressTagService $service)
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
        return JsonResource::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * @param int $id
     * @return JsonResource
     */
    public function show(int $id)
    {
        return JsonResource::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @return JsonResponse|array
     * @throws Throwable
     */
    public function store(): JsonResponse|array
    {
        if ($this->service->create(\request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return JsonResponse|array
     * @throws ValidationException|Throwable
     */
    public function update(int $id): JsonResponse|array
    {
        if ($this->service->update($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return JsonResponse|array
     * @throws Exception
     */
    public function destroy(int $id): JsonResponse|array
    {
        if ($this->service->destroy($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
