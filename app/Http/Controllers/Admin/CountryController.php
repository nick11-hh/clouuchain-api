<?php
/**
 * @Author: h9471
 * @Created: 2020/06/22 11:38
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\HasBatchOperate;
use App\Http\Resources\CountryAreaInfo;
use App\Http\Resources\CountryAreaInfoList;
use App\Http\Resources\CountryAreaNotificationList;
use App\Http\Resources\CountryInfo;
use App\Http\Resources\CouponList as ListResources;
use App\Services\Admin\CountryService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CountryController extends Controller
{
    use HasBatchOperate;

    protected CountryService $service;

    public function __construct(CountryService $service)
    {
        $this->service = $service;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function index(Request $request): array
    {
        return ApiResponseService::success($this->service->countryList($request->input('country_id'), $request->input('area_id')));
    }

    /**
     * @param $id
     * @return CountryInfo
     */
    public function show($id): CountryInfo
    {
        return CountryInfo::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse|array
     */
    public function updateTrans(int $id, Request $request): JsonResponse|array
    {
        if ($this->service->updateCountryTrans($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return AnonymousResourceCollection
     */
    public function areas(int $id): AnonymousResourceCollection
    {
        return CountryAreaInfoList::collection($this->service->areas($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @return JsonResponse|array
     */
    public function deleteAreas(): JsonResponse|array
    {
        if ($this->service->deleteAreas($this->getBatchIds())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 更新排序值
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     * @throws Throwable
     */
    public function updateIndex(Request $request): JsonResponse|array
    {
        if ($this->service->updateIndex($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 批量删除
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

    /**
     *
     * @param int $id
     * @param int $status
     * @return JsonResponse|array
     */
    public function setStatus(int $id ,int $status): JsonResponse|array
    {
        if ($this->service->setStatus($id, (int) (bool) $status)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return CountryAreaInfo
     */
    public function areaInfo(int $id): CountryAreaInfo
    {
        return CountryAreaInfo::make($this->service->areaInfo($id))
            ->additional(ApiResponseService::success());
    }

    /**
     *
     * @param int $id
     * @param int $status
     * @return JsonResponse|array
     */
    public function setAreaStatus(int $id ,int $status): JsonResponse|array
    {
        if ($this->service->setAreaStatus($id, (int) (bool) $status)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param  int  $id
     * @param  Request  $request
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function updateArea(int $id, Request $request): JsonResponse|array
    {
        if ($this->service->updateArea($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param  Request  $request
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function createArea(Request $request): JsonResponse|array
    {
        if ($this->service->createArea($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return AnonymousResourceCollection
     */
    public function getNotificationList(): AnonymousResourceCollection
    {
        return CountryAreaNotificationList::collection($this->service->notificationList())
            ->additional(ApiResponseService::success());
    }

    /**
     * @param  int  $id
     * @return CountryAreaNotificationList
     */
    public function getNotificationInfo(int $id): CountryAreaNotificationList
    {
        return CountryAreaNotificationList::make($this->service->notificationInfo($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function createNotification(): JsonResponse|array
    {
        if ($this->service->createNotification(\request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function updateNotification(int $id): JsonResponse|array
    {
        if ($this->service->updateNotification($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return JsonResponse|array
     */
    public function deleteNotification(int $id): JsonResponse|array
    {
        if ($this->service->deleteNotification($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return JsonResponse|array
     */
    public function getNotificationByAreaId(): JsonResponse|array
    {
        $ids = $this->getBatchIds();

        if ($data = $this->service->getNotificationByAreaId($ids)) {
            return ApiResponseService::success(['content' => $data]);
        }

        return ApiResponseService::error();
    }

    /**
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     */
    public function excelImport(Request $request): JsonResponse|array
    {
        $request->validate(['file' => 'required|file']);

        if ($result = $this->service->excelImport($request->file('file'))) {
            return ApiResponseService::success($result);
        }

        return ApiResponseService::error();
    }

    public function getTemplateTypeList(): array
    {
        return ApiResponseService::success($this->service->getTemplateTypeList());
    }

    /**
     * @return StreamedResponse
     */
    public function downloadExcelTemplate(Request $request): StreamedResponse
    {
        return $this->service->downloadExcelTemplate($request->input('type'));
    }

    /**
     * @param  int  $id
     * @param  Request  $request
     * @return JsonResponse|array
     */
    public function updateRGBColor(int $id, Request $request): JsonResponse|array
    {
        $request->validate(['rgb_color' => 'required|array|max:3|min:3', 'rgb_color.*' => 'integer|between:0,255']);

        $colors = collect($request->input('rgb_color'))->map(fn ($v) => (int) $v)->all();

        if ($this->service->updateRGBColor($id, $colors)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 设置国家热门状态
     * @param int $id
     * @param int $status
     * @return JsonResponse|array
     */
    public function setHot(int $id ,int $status): JsonResponse|array
    {
        if ($this->service->setHot($id, (int) (bool) $status)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
