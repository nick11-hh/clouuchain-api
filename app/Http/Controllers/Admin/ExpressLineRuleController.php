<?php
/**
 * @Author: h9471
 * @Created: 2021/06/28 15:47
 */

namespace App\Http\Controllers\Admin;

use App\Http\Resources\ExpressLineRuleList;
use App\Services\Admin\ExpressLineRuleService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class ExpressLineRuleController extends Controller
{
    protected ExpressLineRuleService $service;

    public function __construct(ExpressLineRuleService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @param $expressLineId
     * @return AnonymousResourceCollection
     */
    public function index($expressLineId)
    {
        return ExpressLineRuleList::collection($this->service->ruleIndex($expressLineId))
            ->additional(ApiResponseService::success());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @param int $ruleId
     * @return ExpressLineRuleList
     */
    public function show(int $id, int $ruleId): ExpressLineRuleList
    {
        unset($id);

        return ExpressLineRuleList::make($this->service->show($ruleId))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  int  $expressLineId
     * @param Request $request
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function store(int $expressLineId, Request $request): JsonResponse|array
    {
        if ($this->service->create($expressLineId, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 更新
     *
     * @param Request $request
     * @param int $expressLineId
     * @param int $id
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function update(Request $request, int $expressLineId, int $id): JsonResponse|array
    {
        unset($expressLineId);

        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 删除
     *
     * @param int $expressLineId
     * @param int $id
     * @return JsonResponse|array
     */
    public function destroy(int $expressLineId, int $id): JsonResponse|array
    {
        unset($expressLineId);

        if ($this->service->destroy($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return array
     */
    public function conditions(): array
    {
        return ApiResponseService::success($this->service->conditions());
    }

    /**
     * @param int $expId
     * @return JsonResponse|array
     */
    public function updateBaseConfig(int $expId): JsonResponse|array
    {
        if ($this->service->updateBaseConfig($expId, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $expId
     * @return JsonResponse|array
     */
    public function updateRemark(int $expId): JsonResponse|array
    {
        if ($this->service->updateRemark($expId, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $expId
     * @return JsonResponse|array
     */
    public function getRemark(int $expId): JsonResponse|array
    {
        if ($data = $this->service->getRemark($expId)) {
            return ApiResponseService::success($data);
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return JsonResponse|array
     */
    public function getAdvanceConditions(int $id): JsonResponse|array
    {
        if ($data = $this->service->getAdvanceConditions($id)) {
            return ApiResponseService::success($data);
        }

        return ApiResponseService::error();
    }
}
