<?php

/**
 * @Author: h9471
 * @Created: 2019/9/9 18:19
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LanguageList;
use App\Jobs\ConvertLanguage;
use App\Models\Scope\CompanyScope;
use App\Models\SuperAdminLanguage;
use App\Services\Admin\LanguageService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use Throwable;

class LanguageController extends Controller
{
    protected $service;

    public function __construct(LanguageService $updateLogService)
    {
        $this->service = $updateLogService;
    }

    /**
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        return LanguageList::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * @param  int  $id
     * @return array
     */
    public function show(int $id): array
    {
        return ApiResponseService::success(LanguageList::make($this->service->show($id)));
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function update(int $id, Request $request): JsonResponse|array
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param Request $request
     * @return JsonResponse|array
     * @throws ValidationException
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
     * @param Request $request
     * @param $id
     * @return JsonResponse|array
     */
    public function destroy(Request $request, $id): JsonResponse|array
    {
        if ($this->service->delete(explode(',', $id))) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return JsonResponse|array
     */
    public function batchDelete(): JsonResponse|array
    {
        if ($this->service->batchDelete()) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function languageCanAdd(): array
    {
        $languages = SuperAdminLanguage::withoutGlobalScope(CompanyScope::class)->get();

        return ApiResponseService::success($languages);
    }

    public function setDefault(int $id)
    {
        if ($this->service->setDefault($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function setStatus(int $id, int $status)
    {
        if ($this->service->setStatus($id, (bool) $status)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return AnonymousResourceCollection
     */
    public function getAvailableLanguages()
    {
        return LanguageList::collection($this->service->availableIndex())
            ->additional(ApiResponseService::success());
    }

    public function refreshTranslate(Request $request): JsonResponse|array
    {
        $code = $request->input('language_code');
        $forceUpdate = $request->input('force_update') ?? false;
        $companyID = $request->user()->company_id;
        $convert = new ConvertLanguage([$companyID], $code, $forceUpdate);
        if (!$code || !key_exists($code, $convert->getCodeMap())) {
            return ApiResponseService::error(message: '不合法的语言编码');
        }
        dispatch($convert);
        return ApiResponseService::success(message: '更新语言请求成功');
    }
}
