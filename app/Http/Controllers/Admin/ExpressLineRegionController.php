<?php
/**
 * @Author: h9471
 * @Created: 2021/06/28 15:47
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Client\ExpressPriceController;
use App\Http\Resources\ExpressLineRegionAllList;
use App\Http\Resources\ExpressLineRegionList;
use App\Http\Resources\ExpressLineRegionInfo;
use App\Http\Resources\ExpressLineRegionServiceList;
use App\Http\Resources\ExpressLineServiceInfo;
use App\Lib\Code;
use App\Services\Admin\ExpressLinePriceService;
use App\Services\Admin\ExpressLineRegionService;
use App\Services\Admin\ExpressLineServiceService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use App\Exceptions\AccidentException;

class ExpressLineRegionController extends Controller
{
    protected ExpressLineRegionService $service;

    public function __construct(ExpressLineRegionService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @param int $expressLineId
     * @return AnonymousResourceCollection
     */
    public function index(int $expressLineId): AnonymousResourceCollection
    {
        return ExpressLineRegionList::collection($this->service->regionIndex($expressLineId))
            ->additional(ApiResponseService::success());
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
     * Display the specified resource.
     *
     * @param  int  $id
     * @param $regionId
     * @return ExpressLineRegionInfo
     */
    public function show(int $id, $regionId): ExpressLineRegionInfo
    {
        return ExpressLineRegionInfo::make($this->service->regionInfo($id, $regionId))
            ->additional(ApiResponseService::success());
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
        if ($this->service->update($id, $expressLineId, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 删除分区
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
     * 删除分区
     * @param int $expressLineId
     * @param Request $request
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function batchDestroy(int $expressLineId, Request $request): JsonResponse|array
    {
        if ($this->service->batchDestroy($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 设置状态
     *
     * @param int $expId
     * @param int $regionId
     * @param int $status
     * @return JsonResponse|array
     * @throws Exception
     */
    public function setStatus(int $expId, int $regionId, int $status): JsonResponse|array
    {
        unset($expId);

        if ($this->service->setStatus($regionId, (bool) $status)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $expId
     * @param int $id
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function updateTrans(int $expId, int $id): JsonResponse|array
    {
        unset($expId);

        if ($this->service->updateTranslateData($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 复制
     *
     * @param int $expId
     * @param int $id
     * @return JsonResponse|array
     */
    public function copy(int $expId, int $id): JsonResponse|array
    {
        if ($this->service->copy($expId, $id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * Display a listing of the resource.
     *
     * @param int $eplId
     * @return AnonymousResourceCollection
     */
    public function all(int $eplId): AnonymousResourceCollection
    {
        return ExpressLineRegionAllList::collection($this->service->regionAll($eplId))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param $id
     * @param ExpressLinePriceService $service
     * @return array
     */
    public function priceTable($id, ExpressLinePriceService $service): array
    {
        return ApiResponseService::success($service->info($id));
    }

    /**
     * @param int $id
     * @param ExpressLinePriceService $service
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function updatePriceTable(int $id, ExpressLinePriceService $service): JsonResponse|array
    {
        if ($service->update($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param Request $request
     * @return array
     * @throws Exception
     */
    public function priceTest(Request $request): array
    {
        $data = $request->validate(['region_id' => 'required', 'weight' => 'required|integer|gt:0', 'profit_value' => 'nullable|numeric']);

        return ExpressPriceController::priceTest($data['region_id'], $data['weight'], $data['profit_value'] ?? 0);
    }

    /**
     * @param int $id
     * @param ExpressLinePriceService $service
     * @return JsonResponse|array
     * @throws Exception
     */
    public function exportPriceTable(int $id, ExpressLinePriceService $service): JsonResponse|array
    {
        if ($service->export($id)) {
            return ApiResponseService::success(message: '导出任务添加成功,请到顶部订单下载管理中查看进度和下载');
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @param ExpressLinePriceService $service
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     */
    public function importPriceTable(int $id, ExpressLinePriceService $service, Request $request): JsonResponse|array
    {
        $file = $request->file('file');

        if (! $file) {
            return ApiResponseService::error(message: '导入文件加载失败');
        }

        if ($service->import($id, $file)) {
            return ApiResponseService::success(message:'导入任务添加成功,请到顶部订单下载管理中查看进度和下载');
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @param ExpressLineServiceService $service
     * @return AnonymousResourceCollection
     */
    public function serviceIndex(int $id, ExpressLineServiceService $service): AnonymousResourceCollection
    {
        return ExpressLineRegionServiceList::collection($service->serviceIndex($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param $expId
     * @param $id
     * @param ExpressLineServiceService $service
     * @return ExpressLineServiceInfo
     */
    public function showService($expId, $id, ExpressLineServiceService $service): ExpressLineServiceInfo
    {
        unset($expId);

        return ExpressLineServiceInfo::make($service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param int $expId
     * @param ExpressLineServiceService $service
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function storeService(int $expId, ExpressLineServiceService $service): JsonResponse|array
    {
        if ($service->create($expId, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $expId
     * @param int $id
     * @param ExpressLineServiceService $service
     * @return JsonResponse|array
     * @throws ValidationException
     */
    public function updateService(int $expId, int $id, ExpressLineServiceService $service): JsonResponse|array
    {
        unset($expId);

        if ($service->update($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $expId
     * @param int $id
     * @param ExpressLineServiceService $service
     * @return JsonResponse|array
     */
    public function destroyService(int $expId, int $id, ExpressLineServiceService $service): JsonResponse|array
    {
        unset($expId);

        if ($service->destroy($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @param int $eplId
     * @param ExpressLineServiceService $service
     * @return JsonResponse|array
     * @throws Exception
     */
    public function getOrderVASList(int $id, int $eplId, ExpressLineServiceService $service): JsonResponse|array
    {
        return ApiResponseService::success($service->getOrderVASList($id, $eplId));
    }

    /**
     * @param int $id
     * @param ExpressLineServiceService $service
     * @return JsonResponse|array
     */
    public function servicePriceExport(int $id, ExpressLineServiceService $service): JsonResponse|array
    {
        if ($service->export($id)) {
            return ApiResponseService::success(message: '导出任务添加成功,请到顶部订单下载管理中查看进度和下载');
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @param Request $request
     * @param ExpressLineServiceService $service
     * @return JsonResponse|array
     * @throws Exception
     */
    public function servicePriceImport(int $id, Request $request, ExpressLineServiceService $service): JsonResponse|array
    {
        $file = $request->file('file') ?? throw new AccidentException('导入文件不存在', Code::OPERATE_FAIL);

        if ($service->import($id, $file)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param Request $request
     * @return JsonResponse|array
     */
    public function updateIndex(Request $request): JsonResponse|array
    {
        if ($this->service->updateIndex($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 导出
     * @param int $id
     * @return JsonResponse|array
     * @throws Exception
     */
    public function exportRegionsTable(int $id): JsonResponse|array
    {
        if ($this->service->export($id)) {
            return ApiResponseService::successMessage('导出任务添加成功,请到顶部下载管理中查看进度和下载');
        }

        return ApiResponseService::error();
    }

    /**
     * 导入
     * @param int $id
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     */
    public function importRegionsTable(int $id, Request $request): JsonResponse|array
    {
        $file = $request->file('file');

        if (! $file) {
            return ApiResponseService::errorMessage('导入文件加载失败');
        }

        return ApiResponseService::success($this->service->import($id, $file));
    }

}
