<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\Admin\GoodsAllocationAreaList;
use App\Http\Resources\Admin\GoodsAllocationList;
use App\Services\Admin\GoodsAllocationService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class GoodsAllocationController extends Controller
{
    protected $service;

    public function __construct(GoodsAllocationService $service)
    {
        $this->service = $service;
    }

    /**
     * @param  int  $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(int $warehouseId)
    {
        return GoodsAllocationAreaList::collection($this->service->indexOfAreas($warehouseId))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param  int  $warehouseId
     * @param  int  $id
     * @return mixed
     */
    public function details(int $warehouseId, int $id)
    {
        return GoodsAllocationList::collection($this->service->showAreaDetails($warehouseId, $id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param  int  $warehouseId
     * @param  int  $id
     * @return GoodsAllocationAreaList
     */
    public function show(int $warehouseId, int $id)
    {
        return GoodsAllocationAreaList::make($this->service->showArea($warehouseId, $id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param int $id
     * @param int $status
     * @return JsonResponse
     * @throws \App\Exceptions\AccidentException
     */
    public function areaUnlock(int $id , int $status)
    {
        if ($this->service->areaUnlock($id, (int) (bool) $status)) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }


    /**
     * 更新
     * @param  int  $warehouseId
     * @param  int  $id
     * @param  Request  $request
     * @return JsonResponse|void
     * @throws \App\Exceptions\AccidentException
     * @throws Throwable
     */
    public function update(int $warehouseId, int $id, Request $request)
    {
        if ($this->service->update($warehouseId, $id, $request->all())) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }

    /**
     * @param int $warehouseId
     * @param Request $request
     * @return JsonResponse|array
     * @throws Throwable
     */
    public function store(int $warehouseId, Request $request)
    {
        if ($this->service->add($warehouseId, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param  int  $warehouseId
     * @param  int  $id
     * @return JsonResponse|array
     * @throws Throwable
     */
    public function destroy(int $warehouseId, int $id)
    {
        if ($this->service->deleteArea($warehouseId, $id)) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    /**
     * @param int $id
     * @return JsonResponse
     * @throws Throwable
     */
    public function destroyCustomLocation(int $id)
    {
        if ($this->service->deleteCustomLocation($id)) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }



    /**
     * @param int $warehouseId
     * @param Request $request
     * @return array
     */
    public function search(int $warehouseId, Request $request)
    {
        $request->validate(
            [
                'keyword' => 'sometimes|nullable',
                'area_id' => 'sometimes|nullable',
                'user_id' => 'sometimes|nullable',
                'isbig' => 'sometimes|nullable|in:0,1',
                'size'   => 'sometimes|nullable|array',
                'weight' => 'sometimes|nullable',
            ]
        );
        return ApiResponseService::success(
            $this->service->searchUsableGoodsAllocation(
                $warehouseId,
                $request->query('area_id'),
                $request->query('keyword') ?? '',
                $request->query('user_id') ?? null,
                $request->input('isbig') ?? 0,
                $request->input('size') ?? [],
                $request->input('weight') ?? 0
            )
        );
    }

    public function getOne(int $warehouseId)
    {
        return ApiResponseService::success($this->service->getOneUsableGoodsAllocation($warehouseId));
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function locationList(Request $request)
    {
        return $this->service->locationList($request);
    }

    /**
     * @param int $id
     * @param int $status
     * @return JsonResponse
     */
    public function setLock(int $id, int $status)
    {
        if ($this->service->setLock($id, $status)) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }

    /**
     * @param int $warehouseId
     * @param int $id
     * @param int $status
     * @return JsonResponse
     */
    public function setLockForWarehouse(int $warehouseId, int $id, int $status)
    {
        if ($this->service->setLock($id, $status)) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }


    /**
     * @param Request $request
     * @return array
     */
    public function getNumberInWarehouse(Request $request)
    {
        return ApiResponseService::success($this->service->getNumberInWarehouse($request->input('warehouse_id', 0)));
    }

    /**
     * 更新排序值
     * @param Request $request
     * @return JsonResponse|void
     * @throws Throwable
     */
    public function updateIndex(Request $request)
    {
        if ($this->service->updateIndex($request->all())) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }

    /**
     * 重置排序值
     *
     * @return JsonResponse|void
     * @throws Throwable
     */
    public function resetIndex()
    {
        if ($this->service->resetIndex()) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }

    /**
     * @param int $areaId
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function addLocations(int $areaId, Request $request)
    {
        if ($this->service->addCustomLocation($areaId, $request->all())) {
            return ApiResponseService::successMessage();
        }

        return ApiResponseService::errorMessage();
    }

    /**
     * @param int $whId
     * @param int $status
     * @return JsonResponse
     */
    public function updateCustomLocationConfig(int $warehouseId, int $status)
    {
        if ($this->service->updateCustomLocation($warehouseId, (bool) $status)) {
            return ApiResponseService::successMessage();
        }
        return ApiResponseService::errorMessage();
    }

    /**
     * @param Request $request
     * @return array
     */
    public function getLocationAreaTree($id)
    {
        return ApiResponseService::success($this->service->getLocationAreaTree($id));
    }
}
