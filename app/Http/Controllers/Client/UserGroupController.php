<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\Client\RouteMenuList;
use App\Http\Resources\Client\UserGroupList;
use App\Lib\Code;
use App\Services\ApiResponseService;
use App\Services\Client\UserGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserGroupController extends Controller
{

    private UserGroupService $service;

    public function __construct(UserGroupService $userGroupService)
    {
        $this->service = $userGroupService;
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        $list = $this->service->index();
        return UserGroupList::collection($list)
            ->additional(ApiResponseService::success());
    }

    /**
     * @param $id
     * @return UserGroupList
     */
    public function show($id)
    {
       $data = $this->service->show($id);
       return UserGroupList::make($data)->additional(ApiResponseService::success());
    }

    public function store(Request $request)
    {
        if ($this->service->store($request->all())) {
            return ApiResponseService::successMessage('保存成功');
        }
        return ApiResponseService::errorMessage('保存失败');
    }

    public function update($id, Request $request)
    {
        if ($this->service->update($id, $request->all())) {
            return ApiResponseService::successMessage('保存成功');
        }
        return ApiResponseService::errorMessage('保存失败');
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

    /**
     * @param int $id
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @throws \Exception
     */
    public function getPermissions(int $id)
    {
        return RouteMenuList::collection($this->service->getPermissions($id))
            ->additional(ApiResponseService::success());
    }


    public function updatePermissions(int $id, Request $request)
    {
        if ($this->service->updatePermissions($id, $request->only('permissions'))) {
            return ApiResponseService::success();
        }

        return ApiResponseService::error();
    }


}
