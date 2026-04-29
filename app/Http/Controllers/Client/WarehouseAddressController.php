<?php
/**
 * @Author: h9471
 * @Created: 2019/9/10 11:38
 */

namespace App\Http\Controllers\Client;

use App\Http\Controllers\HasTranslateOperator;
use App\Http\Resources\PackageWarehouseAddress;
use App\Http\Resources\WarehouseAddressOriginList as Resources;
use App\Services\Admin\WarehouseAddressService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Throwable;

class WarehouseAddressController extends Controller
{
    use HasTranslateOperator;

    protected WarehouseAddressService $service;

    public function __construct(WarehouseAddressService $service)
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
        return Resources::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function simple()
    {
        return ApiResponseService::success($this->service->simple());
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
     * Display a listing of the resource.
     *
     * @return Resources
     */
    public function show(int $id): Resources
    {
        return Resources::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * 新建
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     * @throws Throwable
     */
    public function store(Request $request): JsonResponse|array
    {
        if ($this->service->createAddress($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 更新
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     * @throws Throwable
     */
    public function update(int $id, Request $request): JsonResponse|array
    {
        if ($this->service->updateAddress($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 删除
     *
     * @param int $id
     * @return JsonResponse|array
     * @throws Exception
     * @throws Throwable
     */
    public function delete(int $id): JsonResponse|array
    {
        if ($this->service->delete(Arr::wrap($id))) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return array
     */
    public function getAll(): array
    {
        return ApiResponseService::success($this->service->all());
    }

    /**
     * @return array
     */
    public function getAllWithExpressLines(): array
    {
        return ApiResponseService::success($this->service->allWithExpressLine());
    }

    /**
     * @return array
     */
    public function simpleList(): array
    {
        return ApiResponseService::success(PackageWarehouseAddress::collection($this->service->all()));
    }

    /**
     * @param Request $request
     * @return array
     */
    public function filterList(Request $request): array
    {
        $data = $request->validate([
            'country_id' => 'required|integer',
        ]);

        return ApiResponseService::success($this->service->filterList($data));
    }

    /**
     * @param  int  $id
     * @param  int  $status
     * @return JsonResponse|array
     */
    public function setStatus(int $id, int $status): JsonResponse|array
    {
        if ($this->service->setStatus($id, (bool) $status)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @param int $show
     * @return JsonResponse|array
     */
    public function setShow(int $id, int $show): JsonResponse|array
    {
        if ($this->service->setShow($id, (bool) $show)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
