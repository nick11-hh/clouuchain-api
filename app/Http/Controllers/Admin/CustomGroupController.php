<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CustomGroupList;
use App\Http\Resources\Client\UserGroupList;
use App\Lib\Code;
use App\Services\Admin\CustomGroupService;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomGroupController extends Controller
{
    private CustomGroupService $service;

    public function __construct(CustomGroupService $customGroupService)
    {
        $this->service = $customGroupService;
    }

    /**
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        $list = $this->service->index();
        return CustomGroupList::collection($list)
            ->additional(ApiResponseService::success());
    }

    /**
     * @param $id
     * @return CustomGroupList
     */
    public function show($id)
    {
        $data = $this->service->show($id);
        return CustomGroupList::make($data)->additional(ApiResponseService::success());
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
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
}
