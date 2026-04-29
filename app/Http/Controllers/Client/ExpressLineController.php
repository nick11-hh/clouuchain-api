<?php
/**
 * @Author: h9471
 * @Created: 2019/9/10 11:38
 */

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Client\ExpressController;
use App\Http\Resources\CommonJsonNameList;
use App\Http\Resources\CountryList;
use App\Http\Resources\CountrySimpleList;
use App\Http\Resources\CountryWithAreaList;
use App\Http\Resources\CountryWithoutAreaList;
use App\Http\Resources\ExpressLineAuthInfo;
use App\Http\Resources\ExpressLineCostServiceList;
use App\Http\Resources\ExpressLineExtraRemarkInfo;
use App\Http\Resources\ExpressLineGroupConfig;
use App\Http\Resources\ExpressLineList as ListResources;
use App\Services\Admin\ExpressLineService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExpressLineController extends Controller
{
    protected $service;

    public function __construct(ExpressLineService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $groupId = is_numeric($request->input('group_id')) ? $request->input('group_id') : 0;

        return ListResources::collection($this->service->index($groupId))
            ->additional(ApiResponseService::success());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return array|JsonResponse
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
     * Display the specified resource.
     *
     * @param  $id
     * @return ListResources
     */
    public function show($id): ListResources
    {
        return ListResources::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * 更新
     * @param Request $request
     * @param int $id
     * @return JsonResponse|array
     * @throws Exception
     * @throws Throwable
     */
    public function update(Request $request, int $id): JsonResponse|array
    {
        if ($this->service->updateInformation($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 获取国家列表
     * @return AnonymousResourceCollection
     */
    public function getCountriesList()
    {
        return CountryList::collection($this->service->getCountryList())
            ->additional(ApiResponseService::success());
    }

    /**
     * 获取国家列表
     * @return AnonymousResourceCollection
     */
    public function getEnabledCountryList()
    {
        return CountryList::collection($this->service->getEnabledCountryList())
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * 获取国家列表
     * @return AnonymousResourceCollection
     */
    public function getEnabledSimpleCountryList()
    {
        return CountrySimpleList::collection($this->service->getEnabledSimpleCountryList())
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * 获取国家列表
     * @return AnonymousResourceCollection
     */
    public function getExpressLineCountryList(int $id)
    {
        return CountryList::collection($this->service->getEnabledCountryListByExpressLine($id))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * 获取国家列表
     * @return AnonymousResourceCollection
     */
    public function getCountriesListWithArea()
    {
        return CountryWithAreaList::collection($this->service->getCountryList())
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * 添加国家
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     */
    public function addCountry(Request $request): JsonResponse|array
    {
        $request->validate(
            [
                'country_id' => 'required',
            ]
        );

        $countryId = $request->input('country_id', '');

        if ($this->service->addCountry($countryId)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function searchCountry(Request $request): array
    {
        $needle = $request->input('keyword', '');

        return ApiResponseService::success($this->service->searchCountry($needle ?? ''));
    }

    /**
     * 设置状态
     *
     * @param  int  $id
     * @param  int  $status
     * @return JsonResponse|array
     * @throws Exception
     */
    public function setStatus(int $id, int $status): JsonResponse|array
    {
        if ($this->service->setStatus($id, (bool) $status)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 获取仓库支持国家
     *
     * @param  Request  $request
     * @return AnonymousResourceCollection
     */
    public function getWarehouseCountriesList(Request $request)
    {
        $ids = $request->input('warehouseIds', []);

        return CountryList::collection($this->service->getWarehouseCountryList($ids))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     *
     * @return array
     */
    public function exportAll(): array
    {
        return ApiResponseService::success(['url' => $this->service->allExportExcel()]);
    }

    /**
     * 批量导出
     *
     * @param  Request  $request
     * @return array
     */
    public function batchExport(Request $request): array
    {
        $request->validate(
            [
                'express_line_ids' => 'array|required',
            ]
        );

        $ids = $request->input('express_line_ids', []);

        return ApiResponseService::success(['url' => $this->service->batchExportExcel($ids)]);
    }

    /**
     * 获得快递线路费用
     *
     * @param  int  $id
     * @return AnonymousResourceCollection
     */
    public function getCostsOfExpressLine(int $id)
    {
        return ExpressLineCostServiceList::collection($this->service->getCostsWithExpressLineEnabled($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param  string  $column
     * @return array
     */
    public function getColumnData(string $column): array
    {
        return ApiResponseService::success($this->service->getColumnData($column));
    }

    /**
     * @param  int  $id
     * @return ExpressLineExtraRemarkInfo
     */
    public function getExtraRemark(int $id)
    {
        return ExpressLineExtraRemarkInfo::make($this->service->show($id))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * @param  int  $id
     * @return JsonResponse|array
     * @throws Exception
     */
    public function updateExtraRemark(int $id): JsonResponse|array
    {
        if ($this->service->updateExtraRemarkInfo($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param  int  $id
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

    /**
     * @return JsonResponse
     * @throws Exception
     */
    public function expressFeeQuery()
    {
        return (new ExpressController())->query(\request()->all());
    }

    /**
     * @param int $id
     * @return array
     */
    public function usableSelfPickupStation(int $id): array
    {
        return ApiResponseService::success($this->service->usableSelfPickupStation($id));
    }

    /**
     * @param int $id
     * @return JsonResponse|array
     * @throws Throwable
     */
    public function updateAdvanceSetting(int $id)
    {
        if ($this->service->updateAdvanceSetting($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function copy(int $id): JsonResponse|array
    {
        if ($this->service->copy($id, \request()->all())) {
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

    /**
     * @param int $id
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function updateGroupConfig(int $id): JsonResponse|array
    {
        if ($this->service->updateGroupConfig($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return array
     */
    public function getGroupConfig(int $id): array
    {
        return ApiResponseService::success(ExpressLineGroupConfig::make($this->service->getGroupConfig($id)));
    }

    /**
     * @return array
     */
    public function getDockingTypes(): array
    {
        return ApiResponseService::success($this->service->getDockingTypes());
    }

    /**
     * @return AnonymousResourceCollection
     */
    public function getSimpleList()
    {
        return CommonJsonNameList::collection($this->service->getSimpleList())
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * 设置是否推荐
     *
     * @param  int  $id
     * @param  int  $status
     * @return JsonResponse|array
     * @throws Exception
     */
    public function setRecommend(int $id, int $status): JsonResponse|array
    {
        if ($this->service->setRecommend($id, (bool) $status)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function getChannelCodeList(int $dockingType)
    {
        return ApiResponseService::success($this->service->getChannelCodeList($dockingType));
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     * @throws Throwable
     */
    public function updateDockingSetting(int $id, Request $request): JsonResponse|array
    {
        if ($this->service->updateDockingSetting($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function showAuth(int $id)
    {
        return ExpressLineAuthInfo::make($this->service->showAuth($id))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    public function updateAuth($id,Request $request): JsonResponse|array
    {
        if ($this->service->updateAuth($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
