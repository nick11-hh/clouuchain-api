<?php
/**
 * @Author: h9471
 * @Created: 2021/06/28 15:47
 */

namespace App\Http\Controllers\Admin;

use App\Http\Resources\ExpressLineRegionInfo;
use App\Http\Resources\ExpressLineRegionTemplateList;
use App\Services\Admin\ExpressLineRegionTemplateService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class ExpressLineRegionTemplateController extends Controller
{
    protected ExpressLineRegionTemplateService $service;

    public function __construct(ExpressLineRegionTemplateService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @param $id
     * @return AnonymousResourceCollection
     */
    public function index($id): AnonymousResourceCollection
    {
        return ExpressLineRegionTemplateList::collection($this->service->index($id))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param $tId
     * @param Request $request
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function store($tId, Request $request): JsonResponse|array
    {
        if ($this->service->create($tId, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * Display the specified resource.
     *
     * @param $tId
     * @param $id
     * @return ExpressLineRegionInfo
     */
    public function show($tId, $id): ExpressLineRegionInfo
    {
        unset($tId);

        return ExpressLineRegionInfo::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * 更新
     *
     * @param Request $request
     * @param $tId
     * @param $id
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function update(Request $request, $tId, $id): JsonResponse|array
    {
        unset($tId);

        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 删除分区
     *
     * @param $tId
     * @param $id
     * @return JsonResponse|array
     */
    public function destroy($tId, $id): JsonResponse|array
    {
        unset($tId);

        if ($this->service->destroy($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 设置状态
     *
     * @param int $tId
     * @param int $id
     * @param int $status
     * @return JsonResponse|array
     * @throws Exception
     */
    public function setStatus(int $tId, int $id, int $status): JsonResponse|array
    {
        unset($tId);

        if ($this->service->setStatus($id, (bool) $status)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param  $id
     * @return JsonResponse
     */
    public function updateTrans($id)
    {
        //TODO
    }

    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function groupIndex(): AnonymousResourceCollection
    {
        return JsonResource::collection($this->service->groupIndex())
            ->additional(ApiResponseService::success());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function groupStore(Request $request): JsonResponse|array
    {
        if ($this->service->groupCreate($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * Display the specified resource.
     *
     * @param $id
     * @return array
     */
    public function groupShow($id): array
    {
        return ApiResponseService::success($this->service->groupShow($id));
    }

    /**
     * 更新
     *
     * @param Request $request
     * @param $id
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function groupUpdate(Request $request, $id): JsonResponse|array
    {
        if ($this->service->groupUpdate($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 删除分区
     *
     * @param $id
     * @return JsonResponse|array
     */
    public function groupDestroy($id): JsonResponse|array
    {
        if ($this->service->groupDelete($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function parse(Request $request)
    {
        $request->validate(['region' => 'required|file']);

        if ($data = $this->service->parse($request->file('region'))) {
            return ApiResponseService::success($data);
        }

        return ApiResponseService::error();
    }
}
