<?php

/**
 * @Author: h9471
 * @Created: 2019/9/10 11:38
 */

namespace App\Http\Controllers;

use App\Events\AfterUserRegistered;
use App\Events\UserUpdated;
use App\Exceptions\AccidentException;
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
use App\Models\SerialNumber;
use App\Models\Traits\HasCompanyId;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Admin\IndexDataService;
use App\Services\Admin\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use HasCompanyId,
        HasBatchOperate;

    protected $service;

    public function __construct(UserService $service)
    {
        $this->service = $service;
    }

    public function indexInit()
    {
        return formatRet(1, 'success', $this->service->indexInit());
    }

    public function userSourceList()
    {
        return formatRet(1, 'success', $this->service->userSourceList());
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        return ListResources::collection($this->service->index())
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    public function allUser()
    {
        $data = User::select('id', 'name')->get();
        return ['ret' => 1, 'msg' => 'success', 'data' => $data];
    }

    /**
     * 邀请列表
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function indexOfInvitation(int $id)
    {
        return ListResources::collection($this->service->indexOfInvitation($id))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /** 用户包裹入库记录
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function userPackageRecord(int $id, Request $request)
    {
        return UserPackageList::collection($this->service->userPackageRecord($id, $request->all()))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * 优惠券列表
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function indexOfCoupon(int $id)
    {
        return CardCouponList::collection($this->service->indexOfCoupon($id))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * 包裹列表
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function indexOfPackage(int $id)
    {
        return UserPackageList::collection($this->service->indexOfPackages($id))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * 订单列表
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function indexOfOrder(int $id)
    {
        return UserOrderList::collection($this->service->indexOfOrders($id))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * 订单列表
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function indexOfAddress(int $id)
    {
        return UserAddressList::collection($this->service->indexOfAddresses($id))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * 交易流水列表
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function indexOfTransaction(int $id)
    {
        return UserTransactionRecordList::collection($this->service->indexOfTransaction($id))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * 积分流水列表
     *
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function indexOfPoint(int $id)
    {
        return IncomeOutlayRecordList::collection($this->service->indexOfPoint($id))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * 成长值流水列表
     *
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function indexOfGrowth(int $id)
    {
        return IncomeOutlayRecordList::collection($this->service->indexOfGrowthValue($id))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function indexCount(int $id)
    {
        return success($this->service->indexCount($id));
    }

    /**
     * @param int $id
     * @return UserInfo
     */
    public function show(int $id)
    {
        return UserInfo::make($this->service->show($id))
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * @param int $id
     * @param int $groupId
     * @return \Illuminate\Http\JsonResponse|void
     * @throws AccidentException
     */
    public function updateGroup(int $id, int $groupId)
    {
        if ($this->service->updateGroup($id, $groupId)) {
            return formatRet(1, 'success');
        }

        return eRet('failed');
    }

    /**
     * @param int $id
     * @param int $groupId
     * @return \Illuminate\Http\JsonResponse|void
     * @throws AccidentException
     */
    public function batchUpdateGroup(Request $request)
    {
        if ($this->service->batchUpdateGroup($request->all())) {
            return formatRet(1, 'success');
        }

        return eRet('failed');
    }

    /**
     * 禁止登录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws AccidentException
     */
    public function forbidLogin(Request $request)
    {
        $data = $request->only('forbid_id')['forbid_id'] ?? [];

        if ($this->service->forbidLogin($data)) {
            return formatRet(1, 'success');
        }

        return eRet('failed');
    }

    /**
     * 允许登陆
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     * @throws AccidentException
     */
    public function allowLogin(Request $request)
    {
        $data = $request->only('allow_id')['allow_id'] ?? [];

        if ($this->service->allowLogin($data)) {
            return formatRet(1, 'success');
        }

        return eRet('failed');
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCountData()
    {
        return formatRet(1, 'success', IndexDataService::getUserCountData());
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function addUser(Request $request)
    {
        if ($user = $this->service->store($request->all())) {
            return success($user);
        }
        return failed();
    }

    public function userExport()
    {
        $data = [
            'url' => $this->service->userExport(),
        ];

        return formatRet(1, 'success', $data);
    }

    public function userTemplate()
    {
        $data = [
            'url' => $this->service->userExport(true),
        ];

        return formatRet(1, 'success', $data);
    }

    public function user2GroupTemplate()
    {
        $data = [
            'url' => $this->service->user2GroupExport(),
        ];

        return formatRet(1, 'success', $data);
    }

    public function user2TagTemplate()
    {
        $data = [
            'url' => $this->service->user2TagTemplate(),
        ];

        return formatRet(1, 'success', $data);
    }

    /**
     * @return \Illuminate\Http\JsonResponse|void
     * @throws AccidentException
     */
    public function destroy()
    {
        if ($this->service->destroy($this->getBatchDeleteIds())) {
            return success();
        }

        return failed();
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(int $id)
    {
        return success(UserProfileInfo::make((object)($this->service->profile($id))));
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(int $id)
    {
        if ($this->service->update($id, \request()->all())) {
            return success();
        }

        return failed();
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchAgentById()
    {
        $keyword = \request()->input('keyword', '') ?? '';

        return success($this->service->searchAgentById($keyword));
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function userLogs(int $id)
    {
        return success($this->service->userLogs($id));
    }

    /**
     * 合并客户
     *
     * @param int $user_id
     * @param int $targetId
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function merge(int $user_id, int $targetId)
    {
        if ($this->service->merge($user_id, $targetId)) {
            return success();
        }

        return failed();
    }

    public function assignCustomer(Request $request)
    {
        if ($this->service->assignCustomer($request->all())) {
            return success();
        }
        return failed();
    }

    public function assignSale(Request $request)
    {
        if ($this->service->assignSale($request->all())) {
            return success();
        }
        return failed();
    }

    public function templateAssign(Request $request)
    {
        $request->validate(['file' => 'required|file']);

        if ($this->service->templateAssign($request->file('file'))) {
            return success();
        }
        return failed();
    }

    public function templateUpdateGroup(Request $request)
    {
        $request->validate(['file' => 'required|file']);

        if ($this->service->templateUpdateGroup($request->file('file'))) {
            return success();
        }
        return failed();
    }

    public function templateUpdateTag(Request $request)
    {
        $request->validate(['file' => 'required|file']);

        if ($this->service->templateUpdateTag($request->file('file'))) {
            return success();
        }
        return failed();
    }

    /**
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(int $id, Request $request)
    {
        if ($this->service->changePassword($id, $request->all())) {
            return success();
        }

        return failed();
    }

    /**
     * @param int $id
     * @return JsonResponse
     * @throws AccidentException
     */
    public function updateUserToAgentInvite(int $id)
    {
        if ($this->service->updateUserToAgentInvite($id)) {
            return success();
        }

        return failed();
    }

    /**
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function makeTags(Request $request)
    {
        if ($this->service->makeTags($request->all())) {
            return success();
        }

        return failed();
    }

    public function wechatAuth(Request $request)
    {
        if ($this->service->wechatAuth($request->all())) {
            return success();
        }

        return failed();
    }

    public function import(Request $request)
    {
        $request->validate(['template' => 'required|file']);

        if ($data = $this->service->import($request->file('template'))) {
            return success($data);
        }

        return failed();
    }

    /**
     * 优质客户列表
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function highQualityUsers()
    {
        return HighQuantityUsersList::collection($this->service->highQualityUsers())
            ->additional(['ret' => 1, 'msg' => 'success']);
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     * @throws AccidentException
     */
    public function updateBindInfo(int $id, Request $request)
    {
        if ($this->service->updateBindInfo($id, $request->all())) {
            return success();
        }

        return failed();
    }

    public function balanceExport(Request $request)
    {
        if ($this->service->balanceExport($request->all())) {
            return formatRet(1, '导出任务添加成功，请在顶部下载管理检查进度或下载到本地');
        }

        return failed();
    }

    /**
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function updateUid(int $id, Request $request)
    {
        if ($this->service->updateUid($id, $request->all())) {
            return success();
        }

        return failed();
    }
}
