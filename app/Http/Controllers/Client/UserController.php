<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\UserList;
use App\Lib\Code;
use App\Services\ApiResponseService;
use App\Services\Client\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public $service;

    public function __construct(UserService $userService)
    {
        $this->service = $userService;
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        $list = $this->service->index();
        return UserList::collection($list)
            ->additional(ApiResponseService::success());
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        if ($this->service->store($request->all())) {
            return ApiResponseService::successMessage('添加员工成功');
        }
        return ApiResponseService::errorMessage('添加员工失败');
    }

    /**
     * @param $id
     * @param Request $request
     * @return JsonResponse
     */
    public function update($id, Request $request)
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::successMessage('保存成功');
        }
        return ApiResponseService::errorMessage('保存失败');
    }

    /** 更新用户状态
     * @param Request $request
     * @return JsonResponse
     */
    public function updateStatus(Request $request)
    {
        if ($this->service->updateStatus($request->all())) {
            return ApiResponseService::successMessage('操作成功');
        }
        return ApiResponseService::errorMessage('操作失败');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function deletes(Request $request)
    {
        if ($this->service->deletes($request->all())) {
            return ApiResponseService::successMessage('删除成功');
        }
        return ApiResponseService::errorMessage('删除失败');
    }

    /** 修改密码
     * @param $id
     * @param Request $request
     * @return JsonResponse
     */
    public function changePassword($id, Request $request)
    {
        if ($this->service->changePassword($id, $request->all())) {
            return ApiResponseService::successMessage('修改成功');
        }
        return ApiResponseService::errorMessage('修改失败');
    }



}
