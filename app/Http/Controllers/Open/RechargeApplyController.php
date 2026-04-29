<?php

namespace App\Http\Controllers\Open;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\RechargeApplyList;
use App\Services\ApiResponseService;
use App\Services\Open\RechargeApplyService;
use Illuminate\Http\Request;

class RechargeApplyController extends Controller
{
    protected RechargeApplyService $service;

    public function __construct(RechargeApplyService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $list = $this->service->index();
        return RechargeApplyList::collection($list)->additional(ApiResponseService::success());
    }

    public function show($id)
    {
        $data = $this->service->show($id);
        return RechargeApplyList::make($data)->additional(ApiResponseService::success());
    }
}