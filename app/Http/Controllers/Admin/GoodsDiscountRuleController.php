<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\GoodsDiscountRuleList;
use App\Services\Admin\GoodsDiscountRuleService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class GoodsDiscountRuleController extends Controller
{
    protected GoodsDiscountRuleService $service;

    public function __construct(GoodsDiscountRuleService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $list = $this->service->index();
        return GoodsDiscountRuleList::collection($list)->additional(ApiResponseService::success());
    }

    public function show($id)
    {
        $list = $this->service->show($id);
        return GoodsDiscountRuleList::make($list)->additional(ApiResponseService::success());
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

    public function deletes(Request $request)
    {
        if ($this->service->deletes($request->all())) {
            return ApiResponseService::successMessage('删除成功');
        }
        return ApiResponseService::errorMessage('删除失败');
    }


}
