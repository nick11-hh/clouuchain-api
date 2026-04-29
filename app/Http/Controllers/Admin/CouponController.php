<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:37
 */

namespace App\Http\Controllers\Admin;

use App\Exceptions\AccidentException;
use App\Http\Controllers\HasBatchOperate;
use App\Http\Controllers\HasTranslateOperator;
use App\Http\Resources\CouponCodeList;
use App\Http\Resources\CouponInfo;
use App\Http\Resources\CouponList as ListResources;
use App\Http\Resources\CouponNewCustomList;
use App\Http\Resources\ExpressLineWithCountriesList;
use App\Http\Resources\NewCusWelfareInfo;
use App\Http\Resources\UserCouponList as UserCouponListResources;
use App\Models\Coupon;
use App\Services\Admin\CouponService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class CouponController extends Controller
{
    use HasTranslateOperator,
        HasBatchOperate;

    protected $service;

    public function __construct(CouponService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        if ($request->input('type') == Coupon::TYPE_NEW_CUSTOM) {
            return CouponNewCustomList::collection($this->service->index())
                ->additional(ApiResponseService::success());
        }

        return ListResources::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function indexOfUserCoupons(int $id)
    {
        return UserCouponListResources::collection($this->service->indexOfUsers($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JsonResponse| mixed
     * @throws Exception
     * @throws \Throwable
     */
    public function store(Request $request)
    {
        if ($this->service->add($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse|array
     * @throws Exception
     * @throws \Throwable
     */
    public function launchByUser(Request $request, int $id): JsonResponse|array
    {
        if ($this->service->launchByUser($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse|array
     * @throws Exception
     * @throws \Throwable
     */
    public function launch(Request $request, int $id): JsonResponse|array
    {
        if ($this->service->launch($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return ListResources
     */
    public function show(int $id)
    {
        return CouponInfo::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * 优惠券作废
     * @param  int  $id
     * @return JsonResponse|array
     * @throws Exception
     */
    public function disable(int $id): JsonResponse|array
    {
        if ($this->service->setDisabled($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return mixed
     */
    public function getConfiguration()
    {
        return NewCusWelfareInfo::make($this->service->getNewCustomWelfareConfiguration())
            ->additional(ApiResponseService::success());
    }

    /**
     * @param  Request  $request
     * @return JsonResponse|array
     * @throws Exception
     */
    public function updateConfiguration(Request $request): JsonResponse|array
    {
        if ($this->service->updateNewCustomWelfareConfiguration($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 失效用户优惠券
     * @param  int  $userId
     * @param  int  $id
     * @return JsonResponse|array
     * @throws Exception
     */
    public function disableUserCoupons(int $userId, int $id): JsonResponse|array
    {
        Log::channel('single')->debug($userId); //调用该变量,防止空变量传入
        if ($this->service->setUserCouponDisabled(Arr::wrap($id))) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 失效用户优惠券
     *
     * @return JsonResponse|array
     */
    public function disableUserCouponBatch(): JsonResponse|array
    {
        if ($this->service->setUserCouponDisabled($this->getBatchIds())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function getEnabledLines()
    {
        return ExpressLineWithCountriesList::collection($this->service->getEnabledLines())
            ->additional(ApiResponseService::success());
    }

    /**
     * @return JsonResponse|array
     */
    public function export(): JsonResponse|array
    {
        if ($this->service->export()) {
            return ApiResponseService::success(message: '导出添加成功，请在顶部下载管理检查进度或下载到本地');
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function couponCodeIndex(int $id)
    {
        return CouponCodeList::collection($this->service->couponCodeIndex($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param int $id
     * @return JsonResponse|array
     */
    public function setCodeDisable(int $id): JsonResponse|array
    {
        if ($this->service->disable($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     */
    public function createCode(int $id, Request $request): JsonResponse|array
    {
        if ($this->service->createCouponCode($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return array
     */
    public function getUserRelations(): array
    {
        return ApiResponseService::success($this->service->getUserRelations());
    }

    /**
     * @param int $id
     * @return JsonResponse|array
     */
    public function destroy(int $id): JsonResponse|array
    {
        if ($this->service->destroy($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
