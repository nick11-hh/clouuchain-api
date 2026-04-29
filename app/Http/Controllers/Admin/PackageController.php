<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PackageList;
use App\Http\Resources\Admin\SplitPackageList;
use App\Models\Package;
use App\Services\Admin\PackageService;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    protected PackageService $service;

    public function __construct(PackageService $service)
    {
        $this->service = $service;
    }


    public function index()
    {
        $list = $this->service->index();
        return PackageList::collection($list)->additional(ApiResponseService::success());
    }


    public function show($id)
    {
        $data = $this->service->show($id);
        return PackageList::make($data)->additional(ApiResponseService::success());
    }

    public function count()
    {
        return ApiResponseService::success($this->service->count());
    }

    public function subStatusCount($type)
    {
        return ApiResponseService::success($this->service->subStatusCount($type));
    }

    public function mergeAndSplit()
    {

    }

    /**
     * 申请运单
     */
    public function applyLogistics(Request $request)
    {
        if ($this->service->applyLogistics($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    /*
    * 移动到配货中
    */
    public function moveToStock(Request $request)
    {
        if ($this->service->moveToStock($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function split(Request $request)
    {
        if ($this->service->split($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function splitList()
    {
        $list = $this->service->splitList();
        return SplitPackageList::collection($list)->additional(ApiResponseService::success());
    }

    /**
     * 合并包裹
     */
    public function splitRollback(Request $request)
    {
        if ($this->service->splitRollback($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function mergeRollback(Request $request)
    {
        if ($this->service->mergeRollback($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }


    public function merge(Request $request)
    {
        if ($this->service->merge($request->all())) {
            return ApiResponseService::success();
        }
        return ApiResponseService::error();
    }

    public function dataJson()
    {
        $data = [
            'stock_status_list' => convertConstant(Package::STOCK_STATUS_LIST),
            'logistics_status_list' => convertConstant(Package::LOGISTICS_STATUS_LIST),
        ];
        return ApiResponseService::success($data);
    }

    public function mergeAbleList()
    {
        $list = $this->service->mergeAbleList();
        foreach ($list['data'] as &$value) {
            $value['package_list'] = PackageList::collection($value['package_list']);
        }
        $list['status'] = 1;
        return $list;
    }

}
