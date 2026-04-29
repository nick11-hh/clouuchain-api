<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:38
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\HasBatchOperate;
use App\Http\Resources\CardCouponList;
use App\Http\Resources\HighQuantityUsersList;
use App\Http\Resources\IncomeOutlayRecordList;
use App\Http\Resources\UserAddressList;
use App\Http\Resources\UserInfo;
use App\Http\Resources\UserList as ListResources;
use App\Http\Resources\UserOrderList;
use App\Http\Resources\UserPackageList;
use App\Http\Resources\UserProfileInfo;
use App\Http\Resources\UserTransactionRecordList;
use App\Models\User;
use App\Services\Admin\IndexDataService;
use App\Services\Admin\UserService;
use App\Services\ApiResponseService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Throwable;

class UserController extends Controller
{
    use HasBatchOperate;

    protected $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    public function indexInit(): array
    {
        return ApiResponseService::success($this->service->indexInit());
    }

    public function userSourceList(): array
    {
        return ApiResponseService::success($this->service->userSourceList());
    }

    /**
     * Display a listing of the resource.
     *
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        return ListResources::collection($this->service->index())
            ->additional(ApiResponseService::success());
    }

    public function allUser()
    {
        $data = User::select('id', 'name')->get();

        return ApiResponseService::success($data);
    }

    /**
     * 邀请列表
     * @param int $id
     * @return AnonymousResourceCollection
     */
    public function indexOfInvitation(int $id): AnonymousResourceCollection
    {
        return ListResources::collection($this->service->indexOfInvitation($id))
            ->additional(ApiResponseService::success());
    }

    /** 用户包裹入库记录
     * @param int $id
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function userPackageRecord(int $id, Request $request)
    {
        return UserPackageList::collection($this->service->userPackageRecord($id, $request->all()))
            ->additional(ApiResponseService::success());
    }

    /**
     * 优惠券列表
     * @param int $id
     * @return AnonymousResourceCollection
     */
    public function indexOfCoupon(int $id)
    {
        return CardCouponList::collection($this->service->indexOfCoupon($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * 包裹列表
     * @param int $id
     * @return AnonymousResourceCollection
     */
    public function indexOfPackage(int $id)
    {
        return UserPackageList::collection($this->service->indexOfPackages($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * 订单列表
     * @param int $id
     * @return AnonymousResourceCollection
     */
    public function indexOfOrder(int $id)
    {
        return UserOrderList::collection($this->service->indexOfOrders($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * 订单列表
     * @param int $id
     * @return AnonymousResourceCollection
     */
    public function indexOfAddress(int $id)
    {
        return UserAddressList::collection($this->service->indexOfAddresses($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * 交易流水列表
     * @param int $id
     * @return AnonymousResourceCollection
     */
    public function indexOfTransaction(int $id)
    {
        return UserTransactionRecordList::collection($this->service->indexOfTransaction($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * 积分流水列表
     *
     * @param int $id
     * @return AnonymousResourceCollection
     */
    public function indexOfPoint(int $id)
    {
        return IncomeOutlayRecordList::collection($this->service->indexOfPoint($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * 成长值流水列表
     *
     * @param int $id
     * @return AnonymousResourceCollection
     */
    public function indexOfGrowth(int $id)
    {
        return IncomeOutlayRecordList::collection($this->service->indexOfGrowthValue($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param int $id
     * @return array
     */
    public function indexCount(int $id): array
    {
        return ApiResponseService::success($this->service->indexCount($id));
    }

    /**
     * @param int $id
     * @return UserInfo
     */
    public function show(int $id)
    {
        return UserInfo::make($this->service->show($id))
            ->additional(ApiResponseService::success());
    }

    /**
     * @param int $id
     * @param int $groupId
     * @return JsonResponse|array
     * @throws Exception
     */
    public function updateGroup(int $id, int $groupId): JsonResponse|array
    {
        if ($this->service->updateGroup($id, $groupId)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param Request $request
     * @return JsonResponse|array
     */
    public function batchUpdateGroup(Request $request): JsonResponse|array
    {
        if ($this->service->batchUpdateGroup($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 禁止登录
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     */
    public function forbidLogin(Request $request): JsonResponse|array
    {
        $data = $request->only('forbid_id')['forbid_id'] ?? [];

        if ($this->service->forbidLogin($data)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * 允许登陆
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     */
    public function allowLogin(Request $request): JsonResponse|array
    {
        $data = $request->only('allow_id')['allow_id'] ?? [];

        if ($this->service->allowLogin($data)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return array
     */
    public function getCountData()
    {
        return ApiResponseService::success(IndexDataService::getUserCountData());
    }

    /**
     * @param Request $request
     * @return JsonResponse|array
     * @throws Throwable
     */
    public function addUser(Request $request): JsonResponse|array
    {
        if ($user = $this->service->store($request->all())) {
            return ApiResponseService::success($user);
        }
        return ApiResponseService::error();
    }

    /**
     * @return JsonResponse|array
     */
    public function userExport(): JsonResponse|array
    {
       if ($this->service->export()) {
           return ApiResponseService::success(message: '导出任务添加成功，请在顶部下载管理检查进度或下载到本地');
       }

        return ApiResponseService::error();
    }

    public function userTemplate(): array
    {
        $data = [
            'url' => $this->service->export(true),
        ];

        return ApiResponseService::success($data);
    }

    public function user2GroupTemplate(): array
    {
        $data = [
            'url' => $this->service->user2GroupExport(),
        ];

        return ApiResponseService::success($data);
    }

    public function user2TagTemplate(): array
    {
        $data = [
            'url' => $this->service->user2TagTemplate(),
        ];

        return ApiResponseService::success($data);
    }

    /**
     * @return JsonResponse|array
     * @throws Exception
     */
    public function destroy(): JsonResponse|array
    {
        if ($this->service->destroy($this->getBatchDeleteIds())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return array
     */
    public function profile(int $id): array
    {
        return ApiResponseService::success(UserProfileInfo::make((object)($this->service->profile($id))));
    }

    /**
     * @param int $id
     * @return JsonResponse|array
     * @throws Exception
     */
    public function update(int $id): JsonResponse|array
    {
        if ($this->service->update($id, \request()->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return array
     */
    public function searchAgentById(): array
    {
        $keyword = \request()->input('keyword', '') ?? '';

        return ApiResponseService::success($this->service->searchAgentById($keyword));
    }

    /**
     * @param int $id
     * @return array
     */
    public function userLogs(int $id): array
    {
        return ApiResponseService::success($this->service->userLogs($id));
    }

    /**
     * 合并客户
     *
     * @param int $user_id
     * @param int $targetId
     * @return JsonResponse|array
     * @throws Exception
     */
    public function merge(int $user_id, int $targetId): JsonResponse|array
    {
        if ($this->service->merge($user_id, $targetId)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function assignCustomer(Request $request): JsonResponse|array
    {
        if ($this->service->assignCustomer($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function assignSale(Request $request): JsonResponse|array
    {
        if ($this->service->assignSale($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function templateAssign(Request $request): JsonResponse|array
    {
        $request->validate(['file' => 'required|file']);

        if ($this->service->templateAssign($request->file('file'))) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function templateUpdateGroup(Request $request): JsonResponse|array
    {
        $request->validate(['file' => 'required|file']);

        if ($this->service->templateUpdateGroup($request->file('file'))) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function templateUpdateTag(Request $request): JsonResponse|array
    {
        $request->validate(['file' => 'required|file']);

        if ($this->service->templateUpdateTag($request->file('file'))) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse|array
     */
    public function changePassword(int $id, Request $request): JsonResponse|array
    {
        if ($this->service->changePassword($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @return JsonResponse|array
     * @throws Exception
     */
    public function updateUserToAgentInvite(int $id): JsonResponse|array
    {
        if ($this->service->updateUserToAgentInvite($id)) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    /**
     * @return JsonResponse|array
     */
    public function makeTags(Request $request): JsonResponse|array
    {
        if ($this->service->makeTags($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function wechatAuth(Request $request): JsonResponse|array
    {
        if ($this->service->wechatAuth($request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function import(Request $request): JsonResponse|array
    {
        $request->validate(['template' => 'required|file']);

        if ($data = $this->service->import($request->file('template'))) {
            return ApiResponseService::success($data);
        }

        return ApiResponseService::error();
    }

    /**
     * 优质客户列表
     * @return AnonymousResourceCollection
     */
    public function highQualityUsers()
    {
        return HighQuantityUsersList::collection($this->service->highQualityUsers())
            ->additional(ApiResponseService::success());
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse|array
     * @throws Exception
     */
    public function updateBindInfo(int $id, Request $request): JsonResponse|array
    {
        if ($this->service->updateBindInfo($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }

    public function balanceExport(Request $request)
    {
        if ($this->service->balanceExport($request->all())) {
            return ApiResponseService::success(message: '导出任务添加成功，请在顶部下载管理检查进度或下载到本地');
        }

        return ApiResponseService::error();
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse|array
     */
    public function updateUid(int $id, Request $request): JsonResponse|array
    {
        if ($this->service->updateUid($id, $request->all())) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }
}
