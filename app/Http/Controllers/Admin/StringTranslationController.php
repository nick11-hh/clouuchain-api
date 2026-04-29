<?php

/**
 * @Author: h9471
 * @Created: 2019/9/9 18:19
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\StringTranslationList;
use App\Services\Admin\StringTranslationService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Throwable;

class StringTranslationController extends Controller
{
    protected StringTranslationService $service;

    public function __construct(StringTranslationService $stringTranslationService)
    {
        $this->service = $stringTranslationService;
    }

    /**
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        return StringTranslationList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * @param  int  $id
     * @return array
     */
    public function show(int $id): array
    {
        return ApiResponseService::success(StringTranslationList::make($this->service->show($id)));
    }

    /**
     * @param  int  $id
     * @param  Request  $request
     * @return JsonResponse|array
     * @throws ValidationException|Throwable
     */
    public function update(int $id, Request $request): JsonResponse|array
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param  Request  $request
     * @return JsonResponse|array
     * @throws ValidationException|Throwable
     */
    public function store(Request $request): JsonResponse|array
    {
        if ($this->service->create($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return array
     */
    public function getEnabledLanguage(): array
    {
        return ApiResponseService::success($this->service->getEnabledLanguage());
    }

    /**
     * @param  int  $id
     * @return JsonResponse|array
     */
    public function destroy(int $id): JsonResponse|array
    {
        if ($this->service->destroy($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function export(): array
    {
        return ApiResponseService::success($this->service->export());
    }

    public function import(Request $request): JsonResponse|array
    {
        if ($this->service->import($request->file('file'))) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

}
